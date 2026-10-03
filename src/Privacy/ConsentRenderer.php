<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final class ConsentRenderer
{
    /** @var list<string> */
    private const CATEGORIES = ['statistics', 'marketing', 'external_media', 'functional', 'unknown'];

    /** @param array<string, mixed> $manifest */
    public function render(
        array $manifest,
        string $assetBaseUrl = '/assets',
        string $locale = 'fr',
    ): string {
        $config = self::normalizeManifest($manifest);
        if ($config['enabled'] !== true || $config['services'] === []) {
            return '';
        }

        $copy = self::copy($locale);
        $assetBaseUrl = self::normalizeAssetBaseUrl($assetBaseUrl);
        $groups = [];
        foreach ($config['services'] as $service) {
            $groups[$service['category']][] = $service['label'];
        }

        $categoryMarkup = [];
        foreach ($groups as $category => $services) {
            $serviceList = implode(', ', array_map(self::escape(...), $services));
            $categoryMarkup[] = sprintf(
                '<label class="dendrila-privacy-consent__category">'
                . '<span><strong>%s</strong><small>%s</small></span>'
                . '<input type="checkbox" value="1" data-dendrila-consent-category-input="%s" aria-label="%s">'
                . '</label>',
                self::escape($copy['categories'][$category] ?? $copy['categories']['unknown']),
                $serviceList,
                self::escape($category),
                self::escape($copy['category_toggle']),
            );
        }

        $clientConfig = [
            'fingerprint' => $config['fingerprint'],
            'retentionDays' => $config['retention_days'],
            'categories' => array_values(array_keys($groups)),
            'locale' => $locale === 'en' ? 'en' : 'fr',
        ];
        $json = json_encode(
            $clientConfig,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );

        $manager = $config['footer_link']
            ? sprintf(
                '<button type="button" class="dendrila-privacy-manage" data-dendrila-consent-open="preferences">%s</button>',
                self::escape($copy['manage']),
            )
            : '';

        return sprintf(
            '<link rel="stylesheet" href="%s/privacy-consent.css" data-dendrila-privacy-asset="consent-style">'
            . '<div id="dendrila-privacy-consent" class="dendrila-privacy-consent dendrila-privacy-consent--%s" hidden>'
            . '<div class="dendrila-privacy-consent__backdrop" data-dendrila-consent-action="close"></div>'
            . '<section class="dendrila-privacy-consent__dialog" role="dialog" aria-modal="true" '
            . 'aria-labelledby="dendrila-privacy-consent-title" aria-describedby="dendrila-privacy-consent-description" tabindex="-1">'
            . '<button type="button" class="dendrila-privacy-consent__close" data-dendrila-consent-action="close" aria-label="%s">×</button>'
            . '<h2 id="dendrila-privacy-consent-title">%s</h2>'
            . '<p id="dendrila-privacy-consent-description">%s</p>'
            . '<div data-dendrila-consent-view="summary">'
            . '<div class="dendrila-privacy-consent__actions">'
            . '<button type="button" class="dendrila-privacy-consent__choice" data-dendrila-consent-action="reject-all">%s</button>'
            . '<button type="button" class="dendrila-privacy-consent__secondary" data-dendrila-consent-action="preferences">%s</button>'
            . '<button type="button" class="dendrila-privacy-consent__choice" data-dendrila-consent-action="accept-all">%s</button>'
            . '</div></div>'
            . '<form data-dendrila-consent-view="preferences" hidden>'
            . '<fieldset><legend>%s</legend>%s</fieldset>'
            . '<div class="dendrila-privacy-consent__actions">'
            . '<button type="button" class="dendrila-privacy-consent__secondary" data-dendrila-consent-action="summary">%s</button>'
            . '<button type="submit" class="dendrila-privacy-consent__choice">%s</button>'
            . '</div></form>'
            . '</section></div>'
            . '%s'
            . '<script type="application/json" id="dendrila-privacy-consent-config">%s</script>'
            . '<script src="%s/privacy-consent.js" defer data-dendrila-privacy-asset="consent-script"></script>',
            self::escape($assetBaseUrl),
            self::escape($config['layout']),
            self::escape($copy['close']),
            self::escape($copy['title']),
            self::escape($copy['description']),
            self::escape($copy['reject_all']),
            self::escape($copy['customize']),
            self::escape($copy['accept_all']),
            self::escape($copy['preferences_title']),
            implode('', $categoryMarkup),
            self::escape($copy['back']),
            self::escape($copy['save']),
            $manager,
            $json,
            self::escape($assetBaseUrl),
        );
    }

    /** @param array<string, mixed> $manifest @return array<string, mixed> */
    private static function normalizeManifest(array $manifest): array
    {
        if (!is_bool($manifest['enabled'] ?? null)
            || !is_string($manifest['layout'] ?? null)
            || !in_array($manifest['layout'], ['bar', 'card'], true)
            || !is_int($manifest['retention_days'] ?? null)
            || $manifest['retention_days'] < 30
            || $manifest['retention_days'] > 365
            || !is_bool($manifest['footer_link'] ?? null)
            || !is_string($manifest['fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/', $manifest['fingerprint'])
            || !is_array($manifest['services'] ?? null)
            || !array_is_list($manifest['services'])
            || count($manifest['services']) > 100) {
            throw new \InvalidArgumentException('Invalid consent manifest.');
        }

        $services = [];
        $seen = [];
        foreach ($manifest['services'] as $service) {
            if (!is_array($service)
                || !is_string($service['id'] ?? null)
                || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $service['id'])
                || isset($seen[$service['id']])
                || !is_string($service['label'] ?? null)
                || trim($service['label']) === ''
                || mb_strlen($service['label']) > 190
                || !is_string($service['category'] ?? null)
                || !in_array($service['category'], self::CATEGORIES, true)) {
                throw new \InvalidArgumentException('Invalid consent service.');
            }
            $seen[$service['id']] = true;
            $services[] = [
                'id' => $service['id'],
                'label' => trim($service['label']),
                'category' => $service['category'],
            ];
        }

        return [
            'enabled' => $manifest['enabled'],
            'layout' => $manifest['layout'],
            'retention_days' => $manifest['retention_days'],
            'footer_link' => $manifest['footer_link'],
            'fingerprint' => $manifest['fingerprint'],
            'services' => $services,
        ];
    }

    private static function normalizeAssetBaseUrl(string $base): string
    {
        $base = rtrim(trim($base), '/');
        if ($base === '' || str_starts_with($base, '//')) {
            throw new \InvalidArgumentException('Invalid consent asset base URL.');
        }
        if (str_starts_with($base, '/')) {
            return $base;
        }
        if (filter_var($base, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($base, PHP_URL_SCHEME)) === 'https') {
            return $base;
        }
        throw new \InvalidArgumentException('Consent assets must use a root-relative or HTTPS URL.');
    }

    /** @return array<string, mixed> */
    private static function copy(string $locale): array
    {
        if ($locale === 'en') {
            return [
                'title' => 'Your privacy choices',
                'description' => 'Optional services stay blocked until you choose. Closing this window does not mean consent.',
                'accept_all' => 'Accept all',
                'reject_all' => 'Reject all',
                'customize' => 'Customize',
                'preferences_title' => 'Choose optional categories',
                'save' => 'Save my choices',
                'back' => 'Back',
                'close' => 'Close without choosing',
                'manage' => 'Manage my choices',
                'category_toggle' => 'Allow this optional category',
                'categories' => [
                    'statistics' => 'Audience measurement',
                    'marketing' => 'Marketing',
                    'external_media' => 'External content',
                    'functional' => 'Optional features',
                    'unknown' => 'Other optional services',
                ],
            ];
        }

        return [
            'title' => 'Vos choix de confidentialité',
            'description' => 'Les services facultatifs restent bloqués tant que vous n’avez pas choisi. Fermer cette fenêtre ne vaut pas consentement.',
            'accept_all' => 'Tout accepter',
            'reject_all' => 'Tout refuser',
            'customize' => 'Personnaliser',
            'preferences_title' => 'Choisir les catégories facultatives',
            'save' => 'Enregistrer mes choix',
            'back' => 'Retour',
            'close' => 'Fermer sans choisir',
            'manage' => 'Gérer mes choix',
            'category_toggle' => 'Autoriser cette catégorie facultative',
            'categories' => [
                'statistics' => 'Mesure d’audience',
                'marketing' => 'Marketing',
                'external_media' => 'Contenus externes',
                'functional' => 'Fonctions facultatives',
                'unknown' => 'Autres services facultatifs',
            ],
        ];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
