# Creating a theme for Dendrila Retro

[Français](OLDWEB-THEMES.md) · **English**

Canal 57, Borne 02 and Signal 96 are examples. The browser receives HTML and CSS. PHP prepares the rendering on the server and the administration area manages settings. A theme is installed alongside the site code by its maintainer. The catalogue welcomes contributions.

## Rendering contract

| Item | Rule |
| --- | --- |
| HTML | Canal 57 and Borne 02 use [HTML 4.01 Strict](https://www.w3.org/TR/html401/), with its complete doctype and UTF-8 declaration using `meta http-equiv`. Use standard headings, lists, links, forms and images. Signal 96 follows HTML 3.2 practices. |
| CSS | Canal 57 and Borne 02 use [CSS level 2, 1998 recommendation](https://www.w3.org/TR/1998/REC-CSS2-19980512/). Use simple selectors, fixed widths, floats, margins, borders and positioning from the standard. Signal 96 uses CSS 1. |
| JavaScript | No browser-side JavaScript, including embedded scripts, HTML event handlers, `javascript:` URLs and dependencies shipped by the theme. Navigation and forms use links, anchors and HTTP requests. |
| Layout | Fixed, stable width on mobile and desktop. Canal 57's 820 px is one example. Horizontal scrolling is expected on narrow screens, with keyboard access to all zones. |
| Media | Local GIF, PNG and JPEG illustrations. Respect alternative text and the GIF-hiding mode. Preserve proportions and constrain dimensions to the frame. |
| Dependencies | Serve resources from the site. Avoid remote fonts and media, tracking pixels and embedded external content. |
| Accessibility | Use labels, logical reading order, identifiable links, visible focus and readable contrast. Decorative images have `alt=""`. |

Avoid modern features outside the retro profile: `main`, `nav`, `section`, `video`, `canvas`, `data-*`, Flexbox, Grid, CSS variables, rounded CSS corners, CSS animations and width-based media queries. For retro frames, use styles such as `solid`, `double`, `inset`, `outset` or `ridge`. Validation targets HTML/CSS code. Operation in a browser from 2000 must be tested separately.

### Signal 96 and custom backgrounds

Signal 96 follows 1996 HTML practices, CSS 1 and uses twelve local GIFs. The gray-and-black pattern with beige gaps is adapted from Kenney's Pattern Pack Pixel 1.1 (tile_0038), licensed CC0 1.0. All three themes offer a site background choice: theme default, solid color or an image tiled on both axes at its natural size. An uploaded GIF, PNG or JPEG remains available when switching to solid color unless explicitly removed. Uploaded backgrounds follow the draft, publication and restoration rules of other media. For existing Signal 96 installations, apply the original palette to restore the new colors without overwriting images.

## Add a theme

1. Choose a stable lowercase identifier, for example `club-radio`.
2. Add `public/assets/club-radio.css`, based on the [Canal 57 stylesheet](../public/assets/oldweb-fixed-portal.css).
3. To change structure, create `src/Oldweb/club-radio.php` based on the [Canal 57 template](../src/Oldweb/portal-page.php). For mainly stylistic changes, reuse `portal-page.php`.
4. Add an entry to [`PortalThemes::AVAILABLE`](../src/Oldweb/PortalThemes.php).

```php
'club-radio' => [
    'label' => 'Club radio',
    'stylesheet' => 'assets/club-radio.css',
    'template' => 'portal-page.php',
    'defaults' => [
        'banner' => ['background' => '#102040', 'text' => '#FFFFFF', 'border' => 'double'],
    ],
],
```

The optional `defaults` palette applies on initial installation with this theme. On existing sites, changing theme uses the draft appearance settings unless the user applies the original palette of the selected theme. Theme name, colors, borders, fonts and images are previewed and published together.

Borne 02 and Signal 96 also declare a demo `content` file in `examples/oldweb/`. It follows the generic content keys and does not overwrite site identity or custom text. Templates and their resources are packaged by `bin/package-retro.py`. The installer, installed site and static exports use `PortalRenderer`. Demo rendering uses the example content and initial palette. Visual comparison uses those same values in the same browser. A custom site name or different font can change the rendering.

### Translate the theme

Use `example-content.json` for French demo content and `example-content.en.json` for English in `examples/oldweb/`. Keep the same keys in both files and add the files to the package list in `bin/package-retro.py`. Use `$language` for the `lang` attribute and `$tr('French label')` for labels in the template. Add their translations in [`PortalLocale.php`](../src/Oldweb/PortalLocale.php).

### Data available in a PHP template

- `$content` contains plain text. Escape it with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- `$appearance` contains already validated colors, borders and image references. The zone contract is defined in [`PortalAppearance::ZONES`](../src/Oldweb/PortalAppearance.php).
- `$theme` provides the local stylesheet path.
- `$preview` indicates a draft, and `$still` indicates GIF hiding. Include `portal-view.php` to reuse color rendering, GIF handling and draft private-image link functions.
- Preserve the mapping between zones and editor settings. Document hidden zones if the theme does not display all of them. To add an editable zone, update the zone contract, provide fallbacks for older sites and adapt the tests.
- Upload content images through the editor and ship theme-specific decorative assets alongside the code. Avoid raw HTML or CSS supplied by forms. PHP templates and the registry come from maintained code; executable files cannot be uploaded through the administration interface.

## Check before submitting a theme

- Validate rendered pages with the [W3C HTML validator](https://validator.w3.org/) and stylesheets with the [CSS validator](https://jigsaw.w3.org/css-validator/) configured for CSS 2.
- Test with JavaScript disabled, using the keyboard and a narrow window. Check horizontal scrolling, long titles, contrast of edited colors and GIF hiding.
- Test draft image preview, publication and restoration of the previous version. Verify the theme reappears with its settings and images.
- Run `composer test`. Include the theme name, a screenshot, licenses and asset sources in the pull request.

See the [installation and release guide](OLDWEB-FIXED-PORTAL.en.md) for the ZIP export and visual-parity tests.
