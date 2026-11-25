#!/usr/bin/env python3
"""Build a reusable Dendrila Retro ZIP or an optional personal kit."""

import argparse
import hashlib
import io
import re
import secrets
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
INNER_NAME = "Site_a_envoyer.zip"
APP = "dendrila-retro"
EDITOR = "dendrila-retro/oldweb-editor.php"
CORE_FILES = (
    "PortalAppearance.php", "PortalStorage.php", "PortalThemes.php", "PortalRenderer.php",
    "portal-controller.php", "portal-editor.php", "portal-install.php", "portal-page.php",
    "portal-view.php", "portal-borne-02.php", "portal-signal-96.php",
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
        def add(path: str, data: str | bytes) -> None:
            entry = ZipInfo(f"{APP}/{path}", (1980, 1, 1, 0, 0, 0))
            entry.external_attr = 0o100644 << 16
            site.writestr(entry, data, compress_type=ZIP_DEFLATED)

        for name, editor in (("index.php", False), ("oldweb-portal.php", False), ("oldweb-editor.php", True)):
            add(name, (
                "<?php\ndeclare(strict_types=1);\n"
                "define('DENDRILA_RETRO_ENTRY', true);\n"
                f"$editor = {'true' if editor else 'false'};\n"
                "require __DIR__ . '/retro/bootstrap.php';\n"
            ))

        def add_php(source: Path) -> None:
            code = source.read_text(encoding="utf-8")
            if source.name != "bootstrap.php":
                anchor = re.search(r"^namespace [^;]+;", code, re.MULTILINE)
                offset = anchor.end() if anchor else code.index("declare(strict_types=1);") + len("declare(strict_types=1);")
                code = code[:offset] + "\n" + GUARD + code[offset:]
            add(f"retro/{source.name}", code)

        for name in CORE_FILES:
            add_php(ROOT / "src" / "Oldweb" / name)
        for name in ("bootstrap.php", "Standalone.php", "standalone-login.php", "standalone-activation.php", "standalone-preview.php"):
            add_php(ROOT / "packaging" / "retro" / name)

        add("preview.php", "<?php\ndeclare(strict_types=1);\n"
            "define('DENDRILA_RETRO_ENTRY', true);\n"
            "require __DIR__ . '/retro/standalone-preview.php';\n")

        if personal is not None:
            key, bundle_id = personal
            digest = hashlib.sha256(key.encode("ascii")).hexdigest()
            add("retro/installation.php",
                          '<?php\n' + GUARD + f"return ['id' => '{bundle_id}', 'activation' => '{digest}'];\n")
        add("retro/.htaccess", "Require all denied\n")
        for filename in ("portal-content", "borne-02-content", "signal-96-content"):
            raw = (ROOT / "examples" / "oldweb" / (filename + ".json")).read_text(encoding="utf-8")
            add(f"retro/{filename}.php", "<?php\n" + GUARD
                + "return json_decode(<<<'RETRO_JSON'\n" + raw.rstrip("\n")
                + "\nRETRO_JSON, true, 32, JSON_THROW_ON_ERROR);\n")
        for source, target in (
            ("public/assets/oldweb-fixed-portal.css", "assets/oldweb-fixed-portal.css"),
            ("packaging/retro/installer-preview.js", "assets/installer-preview.js"),
            ("LICENSE.md", "LICENSE.md"),
        ):
            add(target, (ROOT / source).read_bytes())
        for theme in ("borne-02", "signal-96"):
            assets = ROOT / "public" / "assets" / theme
            for source in sorted(assets.rglob("*")):
                if source.is_file():
                    add(f"assets/{theme}/{source.relative_to(assets).as_posix()}", source.read_bytes())
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
