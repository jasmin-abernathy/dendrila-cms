<?php
declare(strict_types=1);

namespace Dendrila\Cms\Installation;

/**
 * The real renderer's read-only, database-free showcase, shared across all kits.
 *
 * Demo content and images are NEVER inserted into the newly installed site.
 * Images are locally bundled CC0 photographs (see docs/PREVIEW-IMAGES.md).
 */
final class InstallThemePreview
{
    public static function site(string $mode): array
    {
        self::check($mode);
        return $mode === 'empty'
            ? ['name' => 'Mon site']
            : [
                'name' => 'Atelier des possibles',
                'tagline' => 'Un espace pour imaginer et partager',
            ];
    }

    public static function page(string $mode): array
    {
        self::check($mode);
        if ($mode === 'empty') {
            return [
                'title' => 'Mon site',
                'blocks' => [['type' => 'text', 'text' => 'Bienvenue sur notre site.']],
            ];
        }

        return [
            'title' => 'Des idées qui prennent vie',
            'blocks' => [
                [
                    'type' => 'text',
                    'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere, libero vitae tincidunt feugiat, sem lorem sollicitudin sapien, nec volutpat ipsum magna non erat.',
                ],
                ['type' => 'image', 'media_id' => 'mountains', 'alt' => 'Paysage de montagnes, photographie de démonstration libre (CC0)'],
                ['type' => 'cta', 'label' => 'Découvrir nos activités', 'url' => '#contenu'],
                [
                    'type' => 'rich_text',
                    'text' => 'Praesent commodo, neque ac feugiat finibus, mi nisl cursus velit, in vehicula justo odio vitae metus. Donec quis nunc id libero faucibus ultrices. Suspendisse suscipit diam vitae libero dictum, sed varius mauris sollicitudin.',
                ],
                [
                    'type' => 'cards',
                    'heading' => 'Ce que nous imaginons ensemble',
                    'items' => [
                        [
                            'title' => 'Explorer',
                            'text' => 'Aliquam erat volutpat. Vivamus rutrum metus ut nulla placerat, in facilisis mi porta.',
                            'media_id' => 'forest',
                        ],
                        [
                            'title' => 'Créer',
                            'text' => 'Curabitur fermentum sapien at nibh volutpat, a aliquet mauris feugiat.',
                            'media_id' => 'architecture',
                        ],
                        [
                            'title' => 'Partager',
                            'text' => 'Maecenas mattis felis at lacus tincidunt, vel efficitur mauris placerat.',
                            'media_id' => 'flowers',
                        ],
                    ],
                ],
                [
                    'type' => 'statistics',
                    'heading' => 'Quelques chiffres pour illustrer la mise en page',
                    'items' => [
                        ['value' => '12', 'label' => 'projets imaginés'],
                        ['value' => '04', 'label' => 'rencontres à venir'],
                        ['value' => '100 %', 'label' => 'contenu fictif'],
                    ],
                ],
                [
                    'type' => 'gallery',
                    'heading' => 'Images et inspirations',
                    'media_ids' => ['mountains', 'forest', 'architecture', 'flowers'],
                    'alt' => 'Quatre photographies de démonstration placées en galerie',
                ],
                [
                    'type' => 'rich_text',
                    'text' => 'Sed blandit, urna sed tempor vestibulum, sem turpis luctus augue, non pellentesque sapien tellus nec massa. Phasellus ac risus placerat, sollicitudin lectus sit amet, scelerisque ipsum.',
                ],
                [
                    'type' => 'faq',
                    'heading' => 'Quelques questions fréquentes',
                    'items' => [
                        ['question' => 'À quoi sert cet exemple ?', 'answer' => 'Il permet de comparer les six thèmes avec exactement les mêmes textes et photographies.'],
                        ['question' => 'Ce contenu sera-t-il installé ?', 'answer' => 'Non. Le site nouvellement installé conserve sa page de départ vierge.'],
                    ],
                ],
            ],
        ];
    }

    public static function media(string $mode): array
    {
        self::check($mode);
        if ($mode === 'empty') return [];
        return [
            'mountains' => '/assets/install-demo/mountains.webp',
            'forest' => '/assets/install-demo/forest.webp',
            'architecture' => '/assets/install-demo/architecture.webp',
            'flowers' => '/assets/install-demo/flowers.webp',
        ];
    }

    public static function navigation(string $mode): array
    {
        self::check($mode);
        return $mode === 'demo'
            ? [
                ['label' => 'Accueil', 'url' => '/'],
                ['label' => 'Activités', 'url' => '#contenu'],
                ['label' => 'Galerie', 'url' => '#contenu'],
            ]
            : [['label' => 'Accueil', 'url' => '/']];
    }

    private static function check(string $mode): void
    {
        if (!in_array($mode, ['empty', 'demo'], true)) {
            throw new \InvalidArgumentException('Unknown theme preview mode.');
        }
    }
}
