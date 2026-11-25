#!/usr/bin/env python3
"""Export the three Retro demos and a manifest directly from a built installer."""

import argparse
import hashlib
import json
import subprocess
import tempfile
from pathlib import Path
from zipfile import ZipFile

THEMES = {
    "canal57": ("demo", "assets/oldweb-fixed-portal.css"),
    "borne-02": ("demo/borne-02", "assets/borne-02/"),
    "signal-96": ("demo/signal-96", "assets/signal-96/"),
}


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def export(archive: Path, output: Path, source: str, php: str) -> None:
    manifest = {"source": source, "zip_sha256": digest(archive.read_bytes()), "demos": {}}
    with tempfile.TemporaryDirectory() as sandbox, ZipFile(archive) as package:
        package.extractall(sandbox)
        app = Path(sandbox) / "dendrila-retro"
        for theme, (relative, assets) in THEMES.items():
            target = output / relative
            target.mkdir(parents=True, exist_ok=True)
            rendered = subprocess.check_output([php, str(app / "preview.php"), theme])
            (target / "index.html").write_bytes(rendered)
            files = {}
            for asset in sorted((app / "assets").rglob("*")):
                asset_path = asset.relative_to(app).as_posix()
                if asset.is_file() and (asset_path == assets or (assets.endswith("/") and asset_path.startswith(assets))):
                    content = asset.read_bytes()
                    copy = target / asset_path
                    copy.parent.mkdir(parents=True, exist_ok=True)
                    copy.write_bytes(content)
                    files[asset_path] = digest(content)
            manifest["demos"][theme] = {"path": relative + "/", "html_sha256": digest(rendered), "assets": files}
    (output / "retro-manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--source", required=True, help="Git SHA of the source used to build the archive")
    parser.add_argument("--php", default="php", help="PHP 8.4+ CLI executable")
    args = parser.parse_args()
    export(args.archive, args.output, args.source, args.php)
