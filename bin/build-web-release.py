#!/usr/bin/env python3
"""Build a hosting-file-manager release: Composer runs at build time, never on the host.

Run only after 'composer install --no-dev --optimize-autoloader'.
The extracted directory is private; ONLY its public/ child is the DocumentRoot.
"""
from __future__ import annotations

import argparse
import os
from pathlib import Path, PurePosixPath
import stat
import zipfile

ROOT = Path(__file__).resolve().parent.parent
PACKAGE = "dendrila-cms"
DIRECTORIES = ("bin", "database", "design-styles", "public", "src", "vendor")
ROOT_FILES = ("bootstrap.php", "composer.json", "composer.lock", "LICENSE.md")
SKIPPED = {"__pycache__", ".git", ".github", ".DS_Store", "error_log", ".env", "local.php"}


def package_info() -> str:
    return """DENDRILA CMS — INSTALLATION SANS TERMINAL

1. Dans le gestionnaire de fichiers de votre hébergement, extrayez cette archive
   dans un dossier privé, par exemple /home/VOTRE_COMPTE/apps/dendrila-cms/.
   IMPORTANT : ne déposez pas les dossiers config/, src/, vendor/ ni storage/
   directement dans public_html ou dans un dossier accessible sur le Web.
2. Dans les réglages du domaine de votre hébergeur, faites pointer la racine
   publique (DocumentRoot) UNIQUEMENT vers le sous-dossier dendrila-cms/public/.
   Si cette opération est impossible, ne lancez pas l'installation.
3. Activez PHP 8.5 minimum, HTTPS, et les extensions PHP requises.
   Ouvrez https://VOTRE-DOMAINE/preflight.php pour le contrôle.
4. Créez une base MariaDB VIDE et un utilisateur autorisé dans le panneau
   de votre hébergeur. Notez hôte, port, nom, identifiant et mot de passe.
5. Ouvrez https://VOTRE-DOMAINE/. La page redirige vers /install.php.
6. Dans l'écran « Accès », recopiez le code affiché dans le fichier privé
   config/ownership-proof.txt, à côté de public/ (PAS dans public/).
   Cliquez « Vérifier le fichier et continuer ». Le fichier sera effacé.
7. Choisissez le thème, les modules, la base et le compte propriétaire.
   Enregistrez la clé TOTP dans une application d'authentification.
8. L'administration est accessible sur /admin/ après installation.

Sans Composer, SSH ou terminal côté hébergement. Le paquet comprend l'autoloader
de production. Les mises à jour exigent une sauvegarde des fichiers privés et de
MariaDB, puis une migration et une vérification distinctes.

NE PAS UTILISER ce paquet pour écraser un site existant : installation neuve seulement.
Informations détaillées : docs/INSTALLATION.md dans le dépôt GitHub.
"""


def write_bytes(z: zipfile.ZipFile, name: str, contents: bytes, mode: int = 0o644) -> None:
    info = zipfile.ZipInfo(name, (2026, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    info.external_attr = (stat.S_IFREG | mode) << 16
    z.writestr(info, contents, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def build(output: Path) -> int:
    if not (ROOT / "vendor/autoload.php").is_file():
        raise SystemExit("Missing production autoload: run composer install --no-dev first.")
    if (ROOT / "vendor/composer/installed.php").exists():
        installed = (ROOT / "vendor/composer/installed.php").read_text()
        if "Dendrila\\\\Cms\\\\Tests" in installed:
            raise SystemExit("Package unexpectedly contains dev dependencies.")
    files: list[Path] = []
    for filename in ROOT_FILES:
        path = ROOT / filename
        if not path.is_file():
            raise SystemExit(f"Required file missing: {filename}")
        files.append(path)
    for directory in DIRECTORIES:
        for path in (ROOT / directory).rglob("*"):
            if any(part in SKIPPED or part.startswith(".git") for part in path.relative_to(ROOT).parts):
                continue
            if path.is_symlink():
                raise SystemExit(f"Unsafe symlink in distribution: {path}")
            if path.is_file():
                files.append(path)
    if not (ROOT / "public/.htaccess") in files:
        raise SystemExit("Apache public routing rules missing.")
    output.parent.mkdir(parents=True, exist_ok=True)
    output = output.resolve()
    if ROOT == output or output in files:
        raise SystemExit("Output must be outside the source manifest.")
    with zipfile.ZipFile(output, "w", allowZip64=True) as z:
        for path in sorted(files, key=lambda p: str(p.relative_to(ROOT))):
            logical = PurePosixPath(PACKAGE) / PurePosixPath(path.relative_to(ROOT).as_posix())
            write_bytes(z, logical.as_posix(), path.read_bytes())
        for directory in ("config", "storage"):
            write_bytes(z, f"{PACKAGE}/{directory}/.keep", b"")
        write_bytes(z, f"{PACKAGE}/INSTALLER-MOI.txt", package_info().encode("utf-8"))
    with zipfile.ZipFile(output) as z:
        bad = z.testzip()
        if bad:
            raise SystemExit(f"ZIP failed integrity check: {bad}")
        members = set(z.namelist())
        assert f"{PACKAGE}/vendor/autoload.php" in members
        assert f"{PACKAGE}/public/install.php" in members
        assert f"{PACKAGE}/public/preflight.php" in members
        assert f"{PACKAGE}/public/.htaccess" in members
        assert f"{PACKAGE}/config/.keep" in members
        assert not any("/.git/" in name or "/tests/" in name or "/config/local.php" in name for name in members)
    print(f"Ready: {output} ({output.stat().st_size:,} bytes, {len(files)} files)")
    return 0


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=Path, nargs="?", default=ROOT / "build" / "dendrila-cms-web-install.zip")
    raise SystemExit(build(parser.parse_args().output))
