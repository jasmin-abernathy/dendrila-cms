#!/usr/bin/env python3
"""Build a reusable Dendrila Retro ZIP or an optional personal kit."""

import argparse
import hashlib
import io
import secrets
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

ROOT = Path(__file__).resolve().parents[1]
INNER_NAME = "Site_a_envoyer.zip"
APP = "dendrila-retro"
EDITOR = "dendrila-retro/oldweb-editor.php"
CORE_FILES = (
    "PortalAppearance.php", "PortalStorage.php", "PortalThemes.php",
    "portal-controller.php", "portal-editor.php", "portal-install.php", "portal-page.php",
)
GUARD = """
if (!defined('DENDRILA_RETRO_ENTRY')) {
    http_response_code(404);
    return;
}
"""


def make_site(personal: tuple[str, str] | None = None) -> bytes:
    output = io.BytesIO()
    with ZipFile(output, "w", ZIP_DEFLATED) as site:
        for name, editor in (("index.php", False), ("oldweb-portal.php", False), ("oldweb-editor.php", True)):
            site.writestr(f"{APP}/{name}", (
                "<?php\ndeclare(strict_types=1);\n"
                "define('DENDRILA_RETRO_ENTRY', true);\n"
                f"$editor = {'true' if editor else 'false'};\n"
                "require __DIR__ . '/retro/bootstrap.php';\n"
            ))

        def add_php(source: Path) -> None:
            code = source.read_text(encoding="utf-8")
            if source.name.startswith(("portal-", "standalone-")):
                code = code.replace("declare(strict_types=1);", "declare(strict_types=1);\n" + GUARD, 1)
            site.writestr(f"{APP}/retro/{source.name}", code)

        for name in CORE_FILES:
            add_php(ROOT / "src" / "Oldweb" / name)
        for name in ("bootstrap.php", "Standalone.php", "standalone-login.php", "standalone-activation.php"):
            add_php(ROOT / "packaging" / "retro" / name)

        if personal is not None:
            key, bundle_id = personal
            digest = hashlib.sha256(key.encode("ascii")).hexdigest()
            site.writestr(f"{APP}/retro/installation.php",
                          '<?php\n' + GUARD + f"return ['id' => '{bundle_id}', 'activation' => '{digest}'];\n")
        site.writestr(f"{APP}/retro/.htaccess", "Require all denied\n")
        for source, target in (
            ("examples/oldweb/portal-content.json", "retro/portal-content.json"),
            ("public/assets/oldweb-fixed-portal.css", "assets/oldweb-fixed-portal.css"),
            ("LICENSE.md", "LICENSE.md"),
        ):
            site.writestr(f"{APP}/{target}", (ROOT / source).read_bytes())
    return output.getvalue()


def write_kit(path: Path) -> None:
    key = secrets.token_hex(16)
    bundle_id = secrets.token_hex(12)
    with ZipFile(path, "x", ZIP_DEFLATED) as kit:
        kit.writestr(INNER_NAME, make_site((key, bundle_id)))
        kit.writestr("LIRE-MOI.txt", (
            "INSTALLER DENDRILA RETRO SUR O2SWITCH\n\n"
            "1. Ouvre cPanel puis Gestionnaire de fichiers. Va dans le dossier du domaine\n"
            "   sur lequel tu veux créer le site. Pour le domaine principal, c’est\n"
            "   généralement public_html.\n"
            f"2. Clique sur Téléverser et envoie uniquement {INNER_NAME}.\n"
            "   Reviens dans le dossier, sélectionne ce ZIP et clique sur Extraire.\n"
            "   Un dossier dendrila-retro est créé. Retire ensuite le ZIP du dossier Web.\n"
            "3. Dans ton navigateur, ouvre https://ton-domaine.fr/dendrila-retro/\n"
            "   Remplace ton-domaine.fr par ton domaine. Le site te guide.\n\n"
            "CLÉ D’INSTALLATION (à garder sur ton ordinateur)\n"
            f"{key}\n\n"
            "Après l’installation, le site garde la même adresse. Pour le modifier,\n"
            f"ajoute /{EDITOR} après le nom de ton domaine.\n"
            "L’hébergement doit utiliser PHP 8.4 ou plus récent et HTTPS.\n"
            "Ne téléverse pas ce fichier texte ni le kit complet.\n"
            "Si tu veux installer un deuxième site indépendant, utilise un autre kit.\n"
        ))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output", type=Path)
    parser.add_argument("--personal", action="store_true", help="generate a kit with a predefined private key")
    args = parser.parse_args()
    if args.personal:
        write_kit(args.output)
    else:
        with args.output.open("xb") as output:
            output.write(make_site())
