#!/usr/bin/env bash
# Build and verify a complete Retro release in a new output directory.
set -euo pipefail

output=$(realpath -m "${1:?Indique un nouveau dossier de sortie}")
source_sha="${2:?Indique le SHA du code source}"
if [[ -e "$output" ]]; then
    printf '%s\n' "Le dossier de sortie existe déjà : $output" >&2
    exit 1
fi
cd "$(dirname "$0")/.."
if [[ "$source_sha" != "$(git rev-parse HEAD)" ]] || ! git diff --quiet HEAD -- bin packaging/retro src/Oldweb public/assets examples/oldweb tests; then
    printf '%s\n' 'Le SHA doit correspondre au code source à publier.' >&2
    exit 1
fi
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
mkdir -p "$work/release"/{assets,downloads}
archive="$work/release/downloads/Dendrila_Retro.zip"
python3 bin/package-retro.py "$archive"
node tests/retro-install-run.cjs "$archive" "$work/release/assets"
python3 bin/export-retro-demos.py "$archive" "$work/release" --source "$source_sha" --php "${PHP_BIN:-php}"
python3 - "$work/release" <<'PY'
import hashlib
import json
import sys
from pathlib import Path
release = Path(sys.argv[1])
manifest = release / "retro-manifest.json"
data = json.loads(manifest.read_text(encoding="utf-8"))
data["captures"] = {
    item.relative_to(release).as_posix(): hashlib.sha256(item.read_bytes()).hexdigest()
    for item in sorted((release / "assets").glob("*.webp"))
}
manifest.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")
PY
mkdir "$output"
cp -a "$work/release/." "$output/"
