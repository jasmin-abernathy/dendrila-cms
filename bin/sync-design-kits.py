#!/usr/bin/env python3
"""Vendor a reviewed local design-kit snapshot. Run during development, never from HTTP."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import tempfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('source', type=Path)
parser.add_argument('--revision', required=True, help='Reviewed upstream commit SHA')
args = parser.parse_args()
if not re.fullmatch(r'[a-f0-9]{40}', args.revision):
    parser.error('revision must be a full commit SHA')
source = args.source.resolve(strict=True)
catalog = json.loads((source / 'catalog.json').read_text())
expected = {'simple-illustrated':'simple-illustrated', 'image-forward':'image-centered',
            'quiet-elegant':'quiet-elegant', 'ultra-minimal':'very-minimal',
            'graphic-rhythm':'dynamic-graphic', 'editorial':'editorial'}
if {x['design_style_id']:x['id'] for x in catalog['themes']} != expected:
    raise SystemExit('Catalog must match the six CMS styles')
files = {'catalog.json': (source / 'catalog.json').read_bytes()}
for entry in catalog['themes']:
    if entry['path'] != 'themes/' + entry['id'] or entry['manifest'] != 'theme.json':
        raise SystemExit('Unexpected catalog path')
    theme = source / entry['path']
    manifest = json.loads((theme / 'theme.json').read_text())
    if any(manifest[k] != entry[k] for k in ('id','design_style_id','version')):
        raise SystemExit('Manifest and catalog disagree')
    for path in sorted(theme.rglob('*')):
        if path.is_symlink():
            raise SystemExit('Symlinks are not allowed in the bundle')
        if not path.is_file() or 'preview' in path.relative_to(theme).parts:
            continue
        if path.suffix not in ('.json','.css','.svg'):
            raise SystemExit('Unexpected runtime asset: ' + str(path))
        files[path.relative_to(source).as_posix()] = path.read_bytes()
    if entry['path'] + '/' + manifest['entrypoint'] not in files:
        raise SystemExit('Missing CSS entrypoint')
root = Path(__file__).resolve().parents[1]
target = root / 'public/design-kits'
if target.exists() and not (target / 'bundle-lock.json').is_file():
    raise SystemExit('Refusing to replace a directory not owned by this generator')
staging = Path(tempfile.mkdtemp(prefix='.design-kits-', dir=target.parent))
try:
    for relative, data in files.items():
        path = staging / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)
    lock = {'schema_version':1, 'repository':'jasmin-abernathy/dendrila-design-kits',
            'revision':args.revision, 'files':{p:hashlib.sha256(b).hexdigest() for p,b in sorted(files.items())}}
    (staging / 'bundle-lock.json').write_text(json.dumps(lock, indent=2)+'\n')
    if target.exists():
        shutil.rmtree(target)
    os.replace(staging,target)
finally:
    if staging.exists():shutil.rmtree(staging)
print(f'Bundled {len(files)} runtime files for six themes; no previews or remote assets included.')
