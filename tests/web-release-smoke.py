#!/usr/bin/env python3
"""End-to-end smoke of a real extracted ZIP, without Composer or CLI setup token."""
import argparse
import json
import base64
import hashlib
import hmac
import struct
from http.cookiejar import CookieJar
from pathlib import Path
import os
import re
import subprocess
import tempfile
import time
from urllib.parse import urlencode
from urllib.error import URLError, HTTPError
from urllib.request import build_opener, HTTPCookieProcessor
from zipfile import ZipFile

PORT = 18293
BASE = f"http://127.0.0.1:{PORT}"


def get(opener, url):
    with opener.open(BASE + url, timeout=5) as response:
        return response.status, response.read().decode("utf-8")


def run(archive: Path):
    with tempfile.TemporaryDirectory(prefix="dendrila-extracted-") as directory:
        with ZipFile(archive) as z:
            for name in z.namelist():
                p = Path(name)
                assert not p.is_absolute() and ".." not in p.parts
            z.extractall(directory)
        root = Path(directory) / "dendrila-cms"
        assert (root / "vendor/autoload.php").exists()
        assert (root / "config").exists()
        assert (root / "public/.htaccess").exists()
        env = os.environ.copy()
        env["APP_ENV"] = "local"
        env.pop("DENDRILA_SITE_ID", None)
        env.pop("DB_DSN", None)
        env.pop("DB_USERNAME", None)
        env.pop("DB_PASSWORD", None)
        proc = subprocess.Popen(
            [env.get("PHP_BINARY", "php"), "-S", f"127.0.0.1:{PORT}", "-t", "public", "public/router.php"],
            cwd=root, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        try:
            opener = build_opener(HTTPCookieProcessor(CookieJar()))
            for attempt in range(40):
                try:
                    status, body = get(opener, "/preflight.php")
                    break
                except URLError:
                    if proc.poll() is not None:
                        raise AssertionError("PHP failed before serving requests")
                    time.sleep(0.1)
            else:
                raise AssertionError("PHP server failed to start")
            assert status == 200 and "Les prérequis locaux sont satisfaits" in body, "Preflight failed"
            status, body = get(opener, "/")
            assert status == 200 and "ownership-challenge" in body, "First visit should open installer"
            csrf = re.search(r'name="csrf" value="([a-f0-9]{64})"', body)
            challenge = re.search(r'id="ownership-challenge" class="secret">([a-f0-9]{48})<', body)
            assert csrf and challenge, "Installer must show session-bound challenge and CSRF"
            proof = root / "config/ownership-proof.txt"
            proof.write_text(challenge.group(1) + "\n")
            proof.chmod(0o600)
            form = urlencode({"csrf": csrf.group(1), "auth_method": "file"}).encode()
            with opener.open(BASE + "/install.php", data=form, timeout=6) as response:
                body = response.read().decode("utf-8")
                assert response.status == 200 and 'name="theme"' in body, "Proof did not unlock theme chooser"

            # Validate the actual installation wizard, not only the preview endpoints.
            assert body.count('name="theme"') == 6, "Six selectable themes in the installer"
            assert body.count('class="theme-card__actions"') == 6, "Six theme cards offer links to full HTML previews"
            assert body.count('mode=empty') == 6, "Six blank-site links only (not thumbnail default)"
            assert body.count('mode=demo') == 12, "Six rich photo thumbnails plus six full-page demo links"
            for style in ("simple-illustrated", "quiet-elegant", "image-forward",
                          "ultra-minimal", "graphic-rhythm", "editorial"):
                for mode in ("empty", "demo"):
                    status, html = get(opener, "/theme-preview.php?theme=" + style + "&mode=" + mode)
                    assert status == 200, (style, mode, status)
                    assert "data-dendrila-theme=" in html
                    assert ("Bienvenue sur notre site." in html if mode == "empty" else "Des idées qui prennent vie" in html), (style, mode)
                    if mode == "empty":
                        assert 'class="d-gallery"' not in html and 'class="d-card-grid"' not in html
                        assert "/assets/install-demo/" not in html
                    else:
                        assert "Lorem ipsum" in html and 'class="d-card-grid"' in html
                        assert html.count("/assets/install-demo/") >= 7, "Preview must show photos in hero, cards and gallery"
            # Same-domain images must be present in the extracted installation ZIP:
            # no Wikimedia/browser tracking, no network dependency during previews.
            for name in ("mountains", "forest", "architecture", "flowers"):
                image_path = root / "public" / "assets" / "install-demo" / (name + ".webp")
                assert image_path.is_file() and image_path.stat().st_size > 1024
                with opener.open(BASE + "/assets/install-demo/" + name + ".webp", timeout=5) as response:
                    body_bytes = response.read()
                    assert response.status == 200 and body_bytes[:4] == b"RIFF"
                    assert body_bytes[8:12] == b"WEBP"
            try:
                get(opener, "/theme-preview.php?theme=editorial&mode=unknown")
            except HTTPError as e:
                assert e.code == 404
            else:
                raise AssertionError("Invalid preview mode must fail closed")
            assert not proof.exists(), "Ownership proof must be consumed after use"
            assert not (root / "config/install-token.json").exists(), "No CLI token should be needed"
            private = root / "config/private-health.txt"
            private.write_text("PRIVATE_CONTENT_DO_NOT_EXPOSE")
            try:
                _, body = get(opener, "/config/private-health.txt")
            except HTTPError as denied:
                assert denied.code in (403, 404, 503), "Private path should not be served directly"
                body = denied.read().decode("utf-8")
            assert "PRIVATE_CONTENT_DO_NOT_EXPOSE" not in body, "Private file exposed by web router"
            private.unlink()
            # Finish the wizard against MariaDB FROM THE EXTRACTED ZIP, not the checkout.
            if not os.environ.get("PACKAGE_DB_NAME"):
                raise AssertionError("PACKAGE_DB_NAME required for full package validation")
            def submit(fields):
                _, current = get(opener, "/install.php")
                fields["csrf"] = re.search(r'name="csrf" value="([a-f0-9]{64})"', current).group(1)
                with opener.open(BASE + "/install.php", data=urlencode(fields, doseq=True).encode(), timeout=20) as response:
                    return response.read().decode()
            submit({"theme": "editorial"})
            submit({"addons[]": ["media", "forum"]})
            account = submit({"host": "127.0.0.1", "port": "3306", "name": os.environ["PACKAGE_DB_NAME"], "user": os.environ["PACKAGE_DB_USER"], "password": os.environ["PACKAGE_DB_PASSWORD"]})
            secret = re.search(r'<code class="secret">([^<]+)', account).group(1)
            key = base64.b32decode(secret + "=" * ((8 - len(secret) % 8) % 8))
            digest = hmac.new(key, struct.pack(">Q", int(time.time()) // 30), hashlib.sha1).digest()
            offset = digest[-1] & 15
            code = str((struct.unpack(">I", digest[offset:offset+4])[0] & 0x7fffffff) % 1000000).zfill(6)
            completed = submit({"name": "Package test", "url": "https://package.example.test", "email": "package@example.test", "password": "Package-test-only-password", "code": code})
            assert "Votre site est prêt" in completed
            assert (root / "config/local.php").is_file()
            status, homepage = get(opener, "/")
            assert status == 200 and "Bienvenue sur notre site." in homepage
            assert 'data-dendrila-theme="editorial"' in homepage
            print("PASS: extracted package completes real MariaDB installation, account creation and publication")
            print("PASS: release ZIP, Composer autoload, first-visit redirect, preflight, file-manager proof and private directory isolation")
        finally:
            proc.terminate()
            try:proc.wait(timeout=4)
            except subprocess.TimeoutExpired:proc.kill();proc.wait()


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    run(parser.parse_args().archive)
