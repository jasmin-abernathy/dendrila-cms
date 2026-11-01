<?php
declare(strict_types=1);

namespace Dendrila\Cms\Installation;

/**
 * Installation help only: host selection never bypasses file ownership proof.
 * Labels and links are static, curated from official providers' documentation.
 */
final class HostingGuide
{
    /** @return array<string, array{label:string,intro:string,steps:list<string>,note:string,docs:string}> */
    public static function providers(): array
    {
        $finish = 'Ouvrez le dossier « config » situé à côté de « public » (surtout pas à l’intérieur de public).';
        $save = 'Déposez-y le fichier ownership-proof.txt téléchargé ci-dessous, ou créez un fichier portant exactement ce nom et collez le code.';
        return [
            'other' => [
                'label' => 'Autre / Je ne sais pas',
                'intro' => 'Vous n’avez pas besoin de connaître les termes techniques : il suffit de retrouver le gestionnaire des fichiers de votre site.',
                'steps' => [
                    'Connectez-vous à l’espace client de votre hébergeur (celui à qui vous payez l’hébergement, pas forcément le nom de domaine).',
                    'Cherchez « Gestionnaire de fichiers », « Espace web » ou « FTP ».',
                    'Retrouvez le dossier où Dendrila a été déposé : il contient côte à côte les dossiers « public » et « config ». Ne placez rien dans « public ».',
                    $save,
                ],
                'note' => 'Vous ne savez pas qui héberge le site ? Retrouvez l’e-mail de souscription ou la facture. Si une autre personne gère le compte d’hébergement, demandez-lui de déposer le fichier de vérification dans le dossier privé config : vous n’avez pas besoin de ses identifiants.',
                'docs' => '',
            ],
            'o2switch' => [
                'label' => 'o2switch',
                'intro' => 'Chez o2switch, les fichiers peuvent être gérés directement dans cPanel, sans WinSCP ni terminal.',
                'steps' => [
                    'Connectez-vous à cPanel depuis votre espace o2switch.',
                    'Dans « Fichiers », ouvrez « Gestionnaire de fichiers ».',
                    'Naviguez jusqu’au dossier de votre installation Dendrila, celui contenant « public » et « config ».',
                    $finish.' '.$save,
                ],
                'note' => 'Ne vous arrêtez pas dans public_html ou dans le dossier public du site : la preuve doit être rangée dans le dossier privé « config ».',
                'docs' => 'https://faq.o2switch.fr/cpanel/fichiers/gestionnaire-fichiers-web/',
            ],
            'infomaniak' => [
                'label' => 'Infomaniak',
                'intro' => 'Infomaniak propose un outil Web FTP depuis son Manager, accessible dans le navigateur.',
                'steps' => [
                    'Connectez-vous à votre Manager Infomaniak et ouvrez l’hébergement concerné.',
                    'Dans le menu « FTP / SSH », sélectionnez « Web FTP ».',
                    'Retrouvez le dossier d’installation qui contient les dossiers « public » et « config ».',
                    $finish.' '.$save,
                ],
                'note' => 'Le dossier « config » doit être privé. Ne le créez pas dans « public ».',
                'docs' => 'https://www.infomaniak.com/fr/support/faq/1130/acceder-rapidement-au-serveur-web-ftp',
            ],
            'hostinger' => [
                'label' => 'Hostinger',
                'intro' => 'Hostinger utilise hPanel et un gestionnaire de fichiers en ligne.',
                'steps' => [
                    'Dans hPanel, ouvrez « Sites web », puis « Gérer » pour votre site.',
                    'Ouvrez « Gestionnaire de fichiers » ; si disponible, sélectionnez l’accès à tous les fichiers de l’hébergement.',
                    'Retrouvez le dossier Dendrila où « public » et « config » sont côte à côte.',
                    $finish.' '.$save,
                ],
                'note' => 'L’accès aux seuls fichiers du domaine peut ouvrir directement « public_html ». Il faut pouvoir atteindre le dossier « config » privé ; ne l’ajoutez pas sous « public_html ».',
                'docs' => 'https://www.hostinger.com/fr/support/4548688-operations-de-base-dans-le-gestionnaire-de-fichiers-chez-hostinger/',
            ],
            'ionos' => [
                'label' => 'IONOS',
                'intro' => 'IONOS propose un « Explorateur d’espace Web » accessible depuis votre navigateur.',
                'steps' => [
                    'Connectez-vous à IONOS, puis ouvrez « Menu » > « Hébergement ».',
                    'Dans la rubrique « Espace Web », choisissez « Utiliser l’espace Web ».',
                    'Retrouvez le dossier d’installation Dendrila, où se trouvent « public » et « config ».',
                    $finish.' '.$save,
                ],
                'note' => 'Si vous n’avez accès qu’à la racine publique, contactez votre hébergeur pour accéder au dossier privé « config ».',
                'docs' => 'https://www.ionos.fr/assistance/websites-shops/websites-und-shops-organisieren/espace-web-utiliser-la-boite-a-outils-de-la-page-daccueil-a-partir-de-decembre-2024/',
            ],
            'ovh' => [
                'label' => 'OVHcloud',
                'intro' => 'Sur OVHcloud, le gestionnaire de fichiers FTP directement dans le navigateur (FTP Explorer) n’est plus disponible.',
                'steps' => [
                    'Connectez-vous à votre espace client OVHcloud et ouvrez l’hébergement web concerné.',
                    'Retrouvez les accès FTP de l’hébergement. OVHcloud recommande un logiciel tel que FileZilla ou Cyberduck.',
                    'Avec l’accès aux fichiers, retrouvez le dossier Dendrila contenant « public » et « config ».',
                    $finish.' '.$save,
                ],
                'note' => 'Si vous êtes sur téléphone sans logiciel FTP ou n’avez pas accès au dossier privé, faites-vous aider pour cette étape. Aucun contournement de sécurité n’est prévu.',
                'docs' => 'https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/ftp-connection',
            ],
        ];
    }

    public static function valid(string $provider): bool
    {
        return array_key_exists($provider, self::providers());
    }

    private static function h(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function databaseGuide(string $provider): string
    {
        if (!self::valid($provider)) $provider='other';
        $guides=[
            'other'=>['Ouvrez les bases de données MySQL/MariaDB dans votre espace client.',
                'Créez une base vide et un utilisateur autorisé à la gérer.', ''],
            'o2switch'=>['Dans cPanel, ouvrez « Bases de données MySQL ».',
                'Créez une base, un utilisateur, puis attribuez-lui les droits. Notez les noms avec leur préfixe cPanel.',
                'https://faq.o2switch.fr/cpanel/bases-de-donnees/mysql/'],
            'infomaniak'=>['Dans Manager, ouvrez votre hébergement puis « Bases de données ».',
                'Ajoutez une base MariaDB et configurez un utilisateur.',
                'https://www.infomaniak.com/fr/support/faq/1981/gerer-les-utilisateurs-et-bases-de-donnees-mysqlmariadb'],
            'hostinger'=>['Dans hPanel, ouvrez « Sites web » puis « Gérer » et « Bases de données ».',
                'Créez une nouvelle base et son utilisateur.',
                'https://www.hostinger.com/fr/support/1583542-comment-faire-pour-creer-une-nouvelle-base-de-donnees-mysql-chez-hostinger/'],
            'ionos'=>['Dans IONOS, ouvrez « Hébergement » puis « Bases de données ».',
                'Créez une base MariaDB et relevez son adresse de serveur.',
                'https://www.ionos.fr/assistance/hebergement/configurer-la-base-de-donnees-mysql/configurer-une-base-de-donnees-mysql-chez-11-ionos/'],
            'ovh'=>['Dans OVHcloud, ouvrez « Web Cloud » puis « Hébergements » et « Bases de données ».',
                'Créez une base compatible si votre offre le permet et relevez son adresse de serveur.',
                'https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/sql-create-database'],
        ];
        [$path,$create,$docs]=$guides[$provider];
        $html='<aside class="hosting-guide setup-db-guide"><strong>Chez '.self::h(self::providers()[$provider]['label'])
            .' :</strong><ol class="hosting-steps"><li>'.self::h($path).'</li><li>'.self::h($create)
            .'</li><li>Recopiez dans les champs suivants les paramètres complets fournis par votre hébergeur.</li></ol>';
        if($docs!=='')$html.='<p><a class="hosting-docs" href="'.self::h($docs)
            .'" target="_blank" rel="noopener noreferrer">Documentation officielle (nouvel onglet)</a></p>';
        return $html.'</aside>';
    }

    public static function render(string $selected): string
    {
        if (!self::valid($selected)) $selected = 'other';
        $providers = self::providers();
        $html = '<label class="field setup-provider-label" for="hosting-provider">'
            . '<strong>Chez qui est hébergé votre site ?</strong>'
            . '<span>Choisissez votre hébergeur pour voir les bonnes étapes.</span>'
            . '<select name="hosting_provider" id="hosting-provider" autocomplete="off">';
        foreach ($providers as $id => $item) {
            $html .= '<option value="'.self::h($id).'"'.($id === $selected ? ' selected' : '').'>'
                .self::h($item['label']).'</option>';
        }
        $html .= '</select></label><div class="hosting-guides" aria-live="polite">';
        foreach ($providers as $id => $item) {
            $html .= '<section class="hosting-guide" data-hosting-guide="'.self::h($id).'"'
                .($id === $selected ? '' : ' hidden')
                .'><p class="hosting-intro">'.self::h($item['intro']).'</p>'
                .'<ol class="hosting-steps">';
            foreach ($item['steps'] as $step) $html .= '<li>'.self::h($step).'</li>';
            $html .= '</ol><p class="hosting-note">'.self::h($item['note']).'</p>';
            if ($item['docs'] !== '') {
                $html .= '<p><a class="hosting-docs" href="'.self::h($item['docs'])
                    .'" target="_blank" rel="noopener noreferrer">Consulter le guide officiel de '
                    .self::h($item['label']).' (nouvel onglet)</a></p>';
            }
            $html .= '</section>';
        }
        $html .= '</div><noscript><p class="hosting-note">Pour adapter le guide sans JavaScript :</p>'
            .'<button class="button secondary" name="show_guide" value="1" type="submit">'
            .'Afficher les étapes de cet hébergeur</button></noscript>';
        return $html;
    }
}
