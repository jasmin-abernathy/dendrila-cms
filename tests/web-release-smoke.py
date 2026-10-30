#!/usr/bin/env python3
"""End-to-end smoke of a real extracted ZIP, without Composer or CLI setup token."""
import argparse
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
            print("PASS: release ZIP, Composer autoload, first-visit redirect, preflight, file-manager proof and private directory isolation")
        finally:
            proc.terminate()
            try:proc.wait(timeout=4)
            except subprocess.TimeoutExpired:proc.kill();proc.wait()


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    run(parser.parse_args().archive)
