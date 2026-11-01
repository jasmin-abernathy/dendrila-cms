<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Dendrila\Cms\Installation\HostingGuide;

function guideCheck(bool $condition,string $message):void {
    if (!$condition) throw new RuntimeException($message);
}
$providers=HostingGuide::providers();
guideCheck(count($providers)===6,'Six host selections, including generic');
guideCheck(array_key_first($providers)==='other','Generic selection is first and safe');
foreach($providers as $id=>$info) {
    guideCheck(HostingGuide::valid($id),'Provider registered: '.$id);
    guideCheck(count($info['steps'])===4,'Host instruction has four steps: '.$id);
    $html=HostingGuide::render($id);
    guideCheck(substr_count($html,'data-hosting-guide=')===6,'All guides are available to JS: '.$id);
    guideCheck(str_contains($html,'<option value="'.$id.'" selected>'),'Dropdown preserves selection: '.$id);
    guideCheck(str_contains($html,'data-hosting-guide="'.$id.'"')
        && !str_contains($html,'data-hosting-guide="'.$id.'" hidden'),'Selected guide visible: '.$id);
    guideCheck(str_contains($html,'show_guide'),'No-JS fallback: '.$id);
    $db=HostingGuide::databaseGuide($id);
    guideCheck(str_contains($db,'hosting-steps')
        && str_contains($db,htmlspecialchars($info['label'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),
        'Database guide corresponds to provider: '.$id);
    if($info['docs']!=='') guideCheck(str_starts_with($info['docs'],'https://'),
        'Official host guide is HTTPS: '.$id);
}
guideCheck(!HostingGuide::valid('<script>'),'Reject unsafe provider ID');
guideCheck(str_contains(HostingGuide::render('unknown'),'<option value="other" selected>'),
    'Unknown provider falls back safely');
guideCheck(str_contains(HostingGuide::databaseGuide('unknown'),'Chez Autre / Je ne sais pas'),
    'Database tutorial falls back safely');

$install=(string)file_get_contents(dirname(__DIR__).'/public/install.php');
guideCheck(str_contains($install,'HostingGuide::render($provider)')
    && str_contains($install,'HostingGuide::databaseGuide('),'Installation uses guides on both file and database steps');
guideCheck(str_contains($install,'session_regenerate_id(true)')
    && str_contains($install,'WebOwnerProof::confirm('),'Owner verification still gates wizard progression');
guideCheck(str_contains($install,'obligatoire si vous installez Dendrila')
    && str_contains($install,'gestionnaire du compte'),
    'Installer explains mandatory self-hosted verification and delegated host account manager.');
guideCheck(!str_contains($install,'nom.dendrila.org'),
    'Installer welcome should not mention the separate hosted-service offer.');

guideCheck(str_contains($install,"header('Cache-Control: no-store, private')")
    && str_contains($install,'download_proof'),'Proof downloaded without caching');
guideCheck(str_contains((string)file_get_contents(dirname(__DIR__).'/public/assets/install-help.js'),'selector.addEventListener'),
    'Provider changes in the browser without navigation');

echo "Hosting setup: 6 host guides, no-JS fallback, safe proof download, database continuation, ownership gate OK\n";
