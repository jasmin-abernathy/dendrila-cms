<?php
declare(strict_types=1);

namespace Dendrila\Cms\Installation;

/**
 * Shared, database-free sample pages for all six real design kits.
 *
 * "empty" mirrors the page created by Installer::install() (no optional addons).
 * "demo" is an explicitly fictional showcase, not imported into the new site.
 */
final class InstallThemePreview
{
    public static function site(string $mode): array
    {
        self::check($mode);
        return $mode === 'empty'
            ? ['name'=>'Mon site']
            : ['name'=>'Atelier des possibles', 'tagline'=>'Un espace pour imaginer et partager'];
    }

    public static function page(string $mode): array
    {
        self::check($mode);
        if ($mode === 'empty') {
            return [
                'title'=>'Mon site',
                'blocks'=>[['type'=>'text', 'text'=>'Bienvenue sur notre site.']],
            ];
        }
        return [
            'title'=>'Des idées qui prennent vie',
            'blocks'=>[
                ['type'=>'text', 'text'=>'Un lieu pour créer, partager et découvrir.'],
                ['type'=>'image', 'media_id'=>'preview', 'alt'=>'Illustration de démonstration'],
                ['type'=>'cta', 'label'=>'Découvrir les projets', 'url'=>'#contenu'],
                ['type'=>'cards', 'heading'=>'Notre univers', 'items'=>[
                    ['title'=>'Rencontrer', 'text'=>'Des projets qui nous rassemblent.'],
                    ['title'=>'Imaginer', 'text'=>'Une autre façon de faire.'],
                    ['title'=>'Partager', 'text'=>'Des ressources à découvrir ensemble.'],
                ]],
                ['type'=>'faq', 'heading'=>'Questions fréquentes', 'items'=>[
                    ['question'=>'Comment participer ?', 'answer'=>'Prenez contact pour découvrir les prochains projets.'],
                ]],
            ],
        ];
    }

    public static function media(string $mode): array
    {
        self::check($mode);
        return $mode === 'demo' ? ['preview'=>'/assets/preview.svg'] : [];
    }

    public static function navigation(string $mode): array
    {
        self::check($mode);
        return $mode === 'demo'
            ? [['label'=>'Accueil','url'=>'/'], ['label'=>'Nos projets','url'=>'#contenu']]
            : [['label'=>'Accueil','url'=>'/']];
    }

    private static function check(string $mode): void
    {
        if (!in_array($mode, ['empty', 'demo'], true)) {
            throw new \InvalidArgumentException('Unknown theme preview mode.');
        }
    }
}
