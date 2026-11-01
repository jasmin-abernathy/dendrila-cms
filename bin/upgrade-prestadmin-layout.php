<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use Dendrila\Cms\Runtime\{Application,AppearanceSettings};
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Authorization\{ActorContext,AuthorizationService};
use Dendrila\Cms\Content\{ContentService,ContentTypeDefinition};

$apply=in_array('--apply',$argv,true);
if(!$apply && !in_array('--dry-run',$argv,true))throw new RuntimeException('Specify --dry-run or --apply');
$base='https://prestadmin-test.lepotager.org';
if(getenv('APP_BASE_URL')!==$base || !str_starts_with(getenv('DB_DSN')?:'','sqlite:'))
    throw new RuntimeException('Must run on Prestadmin private staging SQLite.');
$files=[
 'hero'=>dirname(__DIR__).'/public/assets/prestadmin/hero.webp',
 'about'=>dirname(__DIR__).'/public/assets/prestadmin/about.jpg',
];
foreach($files as $f)if(!is_file($f) || filesize($f)<10000)throw new RuntimeException('Source photo absent or invalid.');
$pdo=Application::database();
$actor=new ActorContext('default','owner');
$store=new PdoCmsStore($pdo);
$service=new ContentService($store,new AuthorizationService($store));
$pages=$service->list($actor,['type'=>'page','limit'=>100]);
$home=null;
foreach($pages as $p)if(($p['slug']??'')==='home'){$home=$p;break;}
if(!$home || $home['status']!=='published')throw new RuntimeException('Home page not published.');
$blocks=$home['values']['blocks'];
foreach($blocks as $item){
    if(in_array(($item['type']??''),['guided_assistant','image_text'],true))
        throw new RuntimeException('Existing guided assistant or image-text block: owner edits must be reviewed.');
}
$highlights=[
    'type'=>'cards','heading'=>'Vos démarches essentielles',
    'items'=>[
        ['title'=>'Facturation électronique',
         'text'=>'Préparez vos données clients : contrôle et remise en ordre, forfait de 39 € jusqu’à 40 clients.',
         'url'=>'/facturation-electronique'],
        ['title'=>'Protection des données (RGPD)',
         'text'=>'Documents, procédures et traceurs : faites le point sur les points à vérifier.',
         'url'=>'https://rgpd.prestadmin57.fr/'],
    ],
];
$about=[
    'type'=>'image_text','heading'=>'Prestadmin, votre appui administratif de confiance',
    'text'=>'Forte de 8 ans d’expérience en gestion administrative, votre interlocutrice accompagne les artisans, indépendants et petites structures de Moselle. Basée à Metz, elle intervient sur site ou à distance selon votre besoin.\n\nLe rôle de Prestadmin : remettre de l’ordre, suivre ce qui compte et vous apporter une vue claire, à jour et transmissible.',
    'image_url'=>$base.'/assets/prestadmin/about.jpg',
    'alt'=>'Dossiers administratifs et matériel de bureau rangés sur une table',
];
$guide=[
    'type'=>'guided_assistant','heading'=>'Vous ne savez pas par où commencer ?',
    'text'=>'L’assistant Prestadmin vous oriente en fonction de votre situation. Choisissez une proposition : rien n’est envoyé avant que vous décidiez de poursuivre.',
    'items'=>[
        ['title'=>'Remettre en ordre mes documents',
         'text'=>'Commencez par décrire les documents et échéances que vous souhaitez organiser. Prestadmin peut aider à trier, classer et prioriser.','url'=>'/services'],
        ['title'=>'Préparer la facturation électronique',
         'text'=>'Vous pouvez consulter les étapes de préparation de la liste clients et les tarifs avant toute commande.','url'=>'/facturation-electronique'],
        ['title'=>'Un besoin particulier ou urgent',
         'text'=>'Décrivez votre situation depuis la page Contact : Prestadmin précisera les tâches à réaliser et les modalités d’intervention.','url'=>'/contact'],
    ],
];
$revised=[];$didIntro=false;$didAbout=false;$didGuide=false;
foreach($blocks as $index=>$block){
    if(!$didAbout && $index>2 && ($block['type']??'')==='text' && str_contains($block['text']??'','8 ans')){
        $revised[]=$about;$didAbout=true;continue;
    }
    if(!$didGuide && ($block['type']??'')==='cta'
        && str_contains((string)($block['url']??''),'/projet/')){
        $revised[]=$guide;$didGuide=true;
    }
    $revised[]=$block;
    if(!$didIntro && ($block['type']??'')==='cta' && $index<=2) {
        $revised[]=['type'=>'cta','label'=>'Être guidé dans ma demande','url'=>'/#assistant'];
        $revised[]=$highlights;$didIntro=true;
    }
}
if(!$didIntro||!$didAbout||!$didGuide)
    throw new RuntimeException('Unexpected homepage shape. Refusing layout override.');
$new=$home['values'];$new['blocks']=$revised;
$schema=$store->findContentType('default','page');
(new ContentTypeDefinition($schema))->validateValues($new);
$appearance=AppearanceSettings::read($pdo,'default');
if($appearance['theme']!=='ultra-minimal')throw new RuntimeException('Installed theme changed.');
$brand=$appearance['brand'];
if(isset($brand['hero_image_url']) && $brand['hero_image_url']!==$base.'/assets/prestadmin/hero.webp')
    throw new RuntimeException('Owner has selected a different hero image.');
$brand['hero_image_url']=$base.'/assets/prestadmin/hero.webp';
AppearanceSettings::validate($appearance['theme'],$brand);
printf("%s: will add highlight cards, original about photo, guided assistant and editable hero image; keep %d prior blocks.\n",
       $apply?'APPLY':'DRY_RUN',count($blocks));
if(!$apply)exit;
$backupDir=dirname(__DIR__,2).'/backups';
if(!is_dir($backupDir)&&!mkdir($backupDir,0700,true))throw new RuntimeException('No private backup directory');
$backup=$backupDir.'/before-prestadmin-native-layout-'.gmdate('Ymd-His').'.sqlite';
$pdo->exec('VACUUM INTO '.$pdo->quote($backup));
chmod($backup,0600);
$res=$service->updateDraft($actor,$home['content_id'],(int)$home['version'],$new,'prestadmin-native-layout-update-v1');
$next=$res['content'];
$service->transition($actor,$next['id'],'published',$next['version'],'prestadmin-native-layout-publish-v1');
AppearanceSettings::save($pdo,'default',hash('sha256',$appearance['raw']),$appearance['theme'],$brand);
echo "LAYOUT_PUBLISHED=YES\n";
echo "HERO_ASSET_CONFIGURED=YES\n";
echo "PRIVATE_BACKUP=".basename($backup)."\n";
