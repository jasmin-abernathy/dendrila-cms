# Dendrila Retro · Canal 57, Borne 02 and Signal 96

[Français](OLDWEB-FIXED-PORTAL.md) · **English**

Dendrila Retro provides three fixed-width themes: Canal 57 (820 px), Borne 02 (800 px) and Signal 96 (780 px). Canal 57 and Borne 02 use HTML 4.01 Strict and CSS 2. Signal 96 follows HTML 3.2 and CSS 1 practices. Published sites work without JavaScript.

## Install the ZIP

You need hosting with **PHP 8.4 or newer**, **HTTPS** and the **fileinfo**, **mbstring** and **session** extensions. PHP must be able to write to a private directory outside all web roots and their aliases.

**Before extracting the ZIP**, prepare your installation. While an uninitialized site is publicly accessible, the first person to complete the installer becomes its administrator. Install immediately or protect the URL using your hosting provider's authentication before extraction.

1. Download [Dendrila_Retro.zip](../downloads/Dendrila_Retro.zip). Extract it into your domain's public directory, then remove the archive from that directory. If your control panel cannot extract ZIP files, extract it on your computer and upload the complete `dendrila-retro` directory by SFTP. Keep hidden files, including `retro/.htaccess`.
2. Open `https://your-domain.example/dendrila-retro/`, replacing the example domain with your own. If the domain already points to the Retro directory, open the domain's home page.
3. Enter the site name and an optional subtitle, and choose a theme using the interactive preview. Choose your username, enter your password (at least 14 characters, at most 72 bytes), confirm it and click the install button.

The standard setup takes place in your browser, without a key, database or Composer. The administration area opens after installation. Its address ends in `/oldweb-editor.php`.

### Where to upload the ZIP

| Hosting | Directory and checks |
| --- | --- |
| **cPanel**, including o2switch | In **File Manager**, open the domain directory. For the main domain this is often `public_html`. Use **Upload** and **Extract**. [File manager documentation (French)](https://faq.o2switch.fr/cpanel/fichiers/gestionnaire-fichiers-web/) |
| **Plesk** | Find the root under **Websites & Domains → Hosting Settings**, often `httpdocs`. Use the domain's file manager. |
| **hPanel / Hostinger** | Under **Websites** or **Hosting**, open the domain's file manager and locate its public directory. Check the PHP version in the panel. |
| **OVHcloud** | Find the domain root in the control panel. Extract the ZIP locally and upload the complete directory by SFTP. [PHP configuration](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/configure-your-web-hosting) |
| **Infomaniak** | Locate the target directory in the Manager, then upload the complete directory by SFTP. [Target directory](https://www.infomaniak.com/fr/support/faq/963/modifier-le-dossier-dun-site) |
| **Other PHP hosting** | Extract the ZIP and upload the complete directory into the domain root. Check persistent private storage before exposing the URL. |

### VPS, dedicated servers and containers

Use a stable path, for example `/srv/dendrila/www` for the public root and `/srv/dendrila/private` for instance storage. The private directory must exist, be writable by PHP and remain outside all web roots. With PHP-FPM, pass its path to the pool using `env[DENDRILA_RETRO_PRIVATE_HOME]`. For a container, mount a persistent volume at that location and configure the same variable.

Block HTTP access to `dendrila-retro/retro/` in the virtual host if `.htaccess` files are ignored. Keep the data directory and proof file outside all aliases. Pass the HTTPS status to PHP from the server or trusted proxy; do not trust a client-supplied header as proof of HTTPS. Static hosting or hosting without persistent storage cannot run Retro.

### If the installer cannot find a private directory

Retro can detect a home directory under `/home/account/` or use the `HOME` recognized by PHP if the site root is below it. Ask your host for a directory outside all web roots where PHP can read and write. If necessary, ask them to configure `DENDRILA_RETRO_PRIVATE_HOME` and allow the path in `open_basedir`. When updating, keep the existing data directory.

### Optional protected installation

To require a key **before making the URL public**, configure `DENDRILA_RETRO_REQUIRE_KEY=1` on the server or use a personal kit built with `python3 bin/package-retro.py kit-prive.zip --personal`. With that kit, deploy only `Site_a_envoyer.zip` and keep the `LIRE-MOI.txt` file containing the key on your computer.

In host-protected mode, the installer lets you choose a key and download its fingerprint. Place the `dendrila-cle-…txt` file in the private directory recognized by PHP without renaming it, then enter the original key in the installer. An existing proof file keeps the instance in protected mode. If unreadable or invalid, the mode remains locked until a valid proof replaces it. Older personal kits remain protected by their key.

### Updating and moving

For an update, back up the private data directory and extract new files **in the same location**. The shared archive does not include `retro/installation.php`; keep that file if an older personal kit includes it. The shared ZIP uses the application directory path to locate its data. If the path changes when moving the site, reconnect the backup to the new location before putting the site online.

Existing accounts without usernames continue to use only their password. An older installation may contain `retro/*-content.json` examples. After backing up, remove those old examples during the update. The current ZIP provides them as protected PHP files.

### Reproduce the archive and demos

To build the archive, demos and three thumbnails from the same SHA, run `bin/release-retro.sh DIRECTORY GIT_SHA` in a clean checkout. Reproducing screenshots requires PHP 8.4+, Playwright with Chromium, OpenSSL, and Python 3.10+ with Pillow. Set `PHP_BIN`, `PLAYWRIGHT_MODULE` and `CHROMIUM_BIN` to specify the tools if needed.

To run the steps individually:

1. Build the archive: `python3 bin/package-retro.py Dendrila_Retro.zip`.
2. Export content from that archive: `python3 bin/export-retro-demos.py Dendrila_Retro.zip DIRECTORY --source GIT_SHA`. The manifest includes provenance, asset hashes and the archive's SHA-256.
3. Run `node tests/retro-install-run.cjs Dendrila_Retro.zip SCREENSHOTS` with PHP, Chromium/Playwright, Python/Pillow and OpenSSL available. This test installs each theme over local HTTPS, checks visual parity and generates WebP thumbnails for the showcase.

## Advanced installation into an existing Dendrila instance

1. Deploy a Dendrila checkout with its `vendor/` directory generated using `composer install --no-dev`. Hosting must support PHP 8.5 or newer, with fileinfo, mbstring and session. Point the web root at `public/`. You can prepare `vendor/` elsewhere before uploading to hosting without Composer.
2. Configure a **dedicated per-instance directory** readable and writable by PHP, outside the web root and its aliases. Set its absolute path in `DENDRILA_OLDWEB_PORTAL_DIR`.
3. Protect `oldweb-editor.php` using HTTPS web-server authentication. The server must pass the authenticated identity in `REMOTE_USER` and set `HTTPS`. Behind a proxy, translate TLS status and identity using the trusted server configuration. Add editor accounts to `DENDRILA_OLDWEB_PORTAL_EDITORS` (space-separated usernames). Requests without an authorized identity are denied. `oldweb-portal.php` remains public.
4. Open `oldweb-editor.php` over HTTPS and sign in. At first access, enter the site name, subtitle and theme, then click the install button. The wizard initializes settings in the private directory. Later visits open the editor.
5. If necessary, adapt the demo text with a private `content.json`. The [example](../examples/oldweb/portal-content.json) documents the available keys. This optional file takes precedence over the name and subtitle entered in the installer.
6. Set `upload_max_filesize` to at least 1 MiB, `post_max_size` to at least 8 MiB and `max_file_uploads` to at least 6 to accept all form fields. Application limits remain 1 MiB per file, 1,600 px per side and 32 MiB per instance.

Dendrila Retro uses files and requires no SQL database for this installer. Existing installations keep their data and go directly to the editor. Completing the installation form again cannot reset an instance.

Maintainers can add themes to the registry; see the [theme creation guide](OLDWEB-THEMES.en.md).

Entry points are independent. The MMW API, settings and content of existing sites continue to use their usual routes.

## Customize

**General font** changes the base font. Each block also has a font selector with families such as Arial, Verdana, Tahoma, Courier New or Comic Sans MS. The theme font option restores the block's original setting. These choices use locally installed fonts and fallbacks; no font file is downloaded when opening the site.

Select **Canal 57**, **Borne 02** or **Signal 96** in the portal theme selector. To restore the selected theme's default colors and borders, check the corresponding box before saving the draft. Existing colors are kept by default when switching themes, and the checkbox does not affect images. Preview the draft before publishing.

The [Borne 02 illustrations](../public/assets/borne-02/licences/illustrations.txt) are CC0. See the [Signal 96 credits](../public/assets/signal-96/illustrations.txt) for its illustrations and the adapted Tiny Grid background (CC BY-SA 3.0).

Each zone exposes a background color, text color and CSS border choice. The site background may use the theme default, a solid color or an image tiled on both axes at its natural size. The banner, introduction, news, agenda, archives and sidebar each accept an illustration. GIF, PNG and JPEG images are displayed within their frame, preserving their proportions. Visitors can hide GIFs using the corresponding link.

The editor validates file types, dimensions, sizes, CSS values and contrasts on the server. Images receive computed names and remain in private storage. The public entry point only serves published media. Drafts and their media remain accessible to authenticated editors. Mutations require a session token and current revision. Saving, publishing and restoring are separate actions. Writes are serialized and settings updates are atomic.

Uploaded originals stay in the private directory to preserve GIFs and their frames. When maintaining storage quotas, retain the `portal-*` files referenced by `draft`, `published` and `previous` in `appearance.json`. Perform server maintenance outside editing periods, after making a backup.
