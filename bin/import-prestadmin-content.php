<?php
declare(strict_types=1);
/**
 * One-time, replay-safe editorial import from public Prestadmin57 source.
 * Source is read-only, target is explicitly the Dendrila staging SQLite.
 * No orders, private client portals, payment records or user accounts imported.
 */
require dirname(__DIR__).'/bootstrap.php';

use Dendrila\Cms\Runtime\Application;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Authorization\{ActorContext,AuthorizationService};
use Dendrila\Cms\Content\{ContentService,ContentTypeDefinition};
use Dendrila\Cms\Navigation\{NavigationService,PdoNavigationRepository};

$apply=in_array('--apply',$argv,true);
if(!$apply && !in_array('--dry-run',$argv,true)){
    fwrite(STDERR,"Usage: php bin/import-prestadmin-content.php --dry-run|--apply\n");
    exit(2);
}
$source=getenv('HOME').'/prestadmin57.fr';
$expected='https://prestadmin-test.lepotager.org';
if(getenv('APP_BASE_URL')!==$expected
    || !str_starts_with(getenv('DB_DSN')?:'','sqlite:')
    || getenv('DENDRILA_SITE_ID')!=='default') {
    throw new RuntimeException('Import is allowed only for the installed Prestadmin staging SQLite site.');
}
foreach(['index.html','facturation-electronique/index.html','carte/index.html',
    'mentions-legales.html','confidentialite.html'] as $file){
    if(!is_file($source.'/'.$file))throw new RuntimeException('Missing public source: '.$file);
}
function prestDoc(string $file): array {
    global $source;
    $doc=new DOMDocument();
    $old=libxml_use_internal_errors(true);
    try{
        if(!$doc->loadHTMLFile($source.'/'.$file))throw new RuntimeException('Invalid HTML source.');
    }finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    return [$doc,new DOMXPath($doc)];
}
function pNodes(DOMXPath $xp,string $path,?DOMNode $context=null): array {
    $nodes=$xp->query($path,$context);
    return $nodes?iterator_to_array($nodes):[];
}
function pNode(DOMXPath $xp,string $path,?DOMNode $context=null): ?DOMNode {
    $nodes=$xp->query($path,$context);
    return ($nodes && $nodes->length)?$nodes->item(0):null;
}
function pText(?DOMNode $node): string {
    if(!$node)return '';
    return trim(preg_replace('/\s+/u',' ',html_entity_decode($node->textContent,ENT_QUOTES|ENT_HTML5,'UTF-8'))??'');
}
function pFirst(DOMXPath $xp,string $path,?DOMNode $node=null): string {
    return pText(pNode($xp,$path,$node));
}
function pParas(DOMXPath $xp,string $path,?DOMNode $node=null): string {
    $values=[];
    foreach(pNodes($xp,$path,$node) as $candidate){
        $text=pText($candidate);
        if($text!=='' && !in_array($text,$values,true))$values[]=$text;
    }
    return implode("\n\n",$values);
}
function pTextBlock(string $text): array {return ['type'=>'text','text'=>$text];}
function pCards(string $title,array $items):array {
    return ['type'=>'cards','heading'=>$title,'items'=>$items];
}
function pCta(string $text,string $url):array{return ['type'=>'cta','label'=>$text,'url'=>$url];}
function pKeyValues(string $heading,array $items):array {
    return ['type'=>'key_values','heading'=>$heading,'items'=>$items];
}
function pSteps(string $heading,array $items):array{return ['type'=>'steps','heading'=>$heading,'items'=>$items];}
function pCardsFrom(DOMXPath $xp,string $path,string $fallback):array {
    $result=[];
    foreach(pNodes($xp,$path) as $node){
        $title=pFirst($xp,'.//h3',$node);
        if($title==='')continue;
        $body=pParas($xp,'.//p[not(contains(@class,"eyebrow"))]',$node);
        $notes=[];
        foreach(pNodes($xp,'.//ul/li',$node) as $li){$t=pText($li);if($t!=='')$notes[]=$t;}
        if($notes)$body.=($body!==''?"\n":'').implode(' · ',$notes);
        $a=pNode($xp,'.//a[@href]',$node);$href=$a?trim($a->getAttribute('href')):'';
        if(str_starts_with($href,'/projet/'))$href='https://www.prestadmin57.fr'.$href;
        if($href==='/facturation-electronique/')$href='/facturation-electronique';
        $item=['title'=>$title,'text'=>$body?:$fallback];
        if($href!=='' && (str_starts_with($href,'/')||str_starts_with($href,'https://')))$item['url']=$href;
        $result[]=$item;
    }
    return $result;
}
[$home,$hx]=prestDoc('index.html');
[$invoice,$ix]=prestDoc('facturation-electronique/index.html');
[$carte,$cx]=prestDoc('carte/index.html');
[$legal,$lx]=prestDoc('mentions-legales.html');
[$privacy,$px]=prestDoc('confidentialite.html');
$offers=pCardsFrom($hx,'//section[@id="offres"]//article[contains(@class,"offer-card")]','');
$solutions=pCardsFrom($hx,'//section[@id="solutions"]//article[contains(@class,"offer-card")]','');
if(count($offers)!==4||count($solutions)!==4)throw new RuntimeException('Source offers sections changed; review before import.');
$benefits=[];
foreach(pNodes($hx,'//section[contains(@class,"benefits")]//*[contains(concat(" ",normalize-space(@class)," ")," benefit ")]') as $node){
    $h=pFirst($hx,'.//h3',$node);$t=pFirst($hx,'.//p',$node);
    if($h!==''&&$t!=='')$benefits[]=['label'=>$h,'value'=>$t];
}
if(count($benefits)!==5)throw new RuntimeException('Source benefits changed; review before import.');
$about=pParas($hx,'//section[contains(@class,"about")]//p[not(contains(@class,"eyebrow"))]');
$hero=pParas($hx,'//section[contains(@class,"hero")]//p[contains(@class,"hero-lead") or contains(@class,"experience-note")]');
$serviceIntro='Quatre façons de recevoir une aide administrative adaptée à votre situation. Prestadmin accompagne les artisans, indépendants et petites structures de Metz et de Moselle.';
$contactInfo=[
    ['label'=>'Téléphone','value'=>'07 49 63 62 34'],
    ['label'=>'E-mail','value'=>'contact@prestadmin57.fr'],
    ['label'=>'Zone','value'=>'Metz, Moselle et à distance selon la mission'],
    ['label'=>'Horaires','value'=>'Lundi – vendredi : 9 h – 12 h / 14 h – 18 h'],
];
$pricing=[];
foreach(pNodes($ix,'//article[contains(@class,"einvoice-level-card")]') as $node){
    $label=pFirst($ix,'.//h3',$node);
    $price=pFirst($ix,'.//*[contains(@class,"einvoice-level-price") or contains(@class,"level-price")]',$node);
    if($price===''){
        foreach(pNodes($ix,'.//*[contains(@class,"price")]',$node) as $item){$price=pText($item);if($price!=='')break;}
    }
    $desc=pParas($ix,'.//p[not(contains(@class,"kicker")) and not(contains(@class,"price"))]',$node);
    if($label!=='' && $desc!=='')$pricing[]=['title'=>$label,'price'=>$price,'text'=>$desc];
}
if(count($pricing)!==4)throw new RuntimeException('Source pricing options changed; review before import.');
$process=[];
foreach(pNodes($ix,'//section[contains(@class,"einvoice-service")]//ol/li',null) as $li){
    $name=pFirst($ix,'.//strong|.//h4',$li);
    $description=pText($li);
    if($name==='')$name=mb_substr($description,0,65);
    if($description!=='')$process[]=['title'=>$name,'text'=>$description];
}
$factIntro=pParas($ix,'//section[contains(@class,"einvoice-hero")]//p[not(contains(@class,"eyebrow"))]');
$factGuide=[];
foreach(pNodes($ix,'//section[contains(@class,"einvoice-guide")]//h3') as $head){
    $parent=$head->parentNode;$description=$parent?pParas($ix,'.//p',$parent):'';
    if($description!=='')$factGuide[]=['title'=>pText($head),'text'=>$description];
}
$cardIntro=pParas($cx,'//div[contains(@class,"hero")]//p[not(contains(@class,"eyebrow"))]');
$legalText=pParas($lx,'//main//p');
$privacyText=pParas($px,'//main//p');
$values=[
 'home'=>['title'=>'Votre administratif reste organisé, vous avancez sereinement.','slug'=>'home','blocks'=>[
    pTextBlock($hero),pCta('Découvrir les prestations','/services'),
    pCards('Comment pouvons-nous vous aider ?',$offers),
    pCards('Des tâches suivies, une personne qui vérifie.',$solutions),
    pKeyValues('Ce que vous gagnez au quotidien.',$benefits),
    pTextBlock($about),
    pCta('Préparer mon besoin','https://www.prestadmin57.fr/projet/'),
    pCta('Facturation électronique : préparer ma liste clients','/facturation-electronique'),
 ]],
 'services'=>['title'=>'Prestations administratives','slug'=>'services','blocks'=>[
    pTextBlock($serviceIntro),pCards('Comment pouvons-nous vous aider ?',$offers),
    pCards('Outils et accompagnement',$solutions),
    pTextBlock('Les tâches à réaliser et le suivi souhaité sont définis avec vous avant intervention. Les sujets juridiques ou comptables spécialisés sont orientés vers le professionnel compétent.'),
    pCta('Préparer mon besoin','https://www.prestadmin57.fr/projet/'),
 ]],
 'methode'=>['title'=>'Notre méthode','slug'=>'methode','blocks'=>[
    pTextBlock('Prestadmin prépare et suit vos informations administratives : les outils repèrent les éléments manquants, une personne vérifie les dossiers qui demandent une décision ou un suivi humain.'),
    pKeyValues('Ce que vous gagnez au quotidien',$benefits),
    pTextBlock('Prestadmin définit avec vous les tâches à réaliser et le suivi souhaité avant intervention.'),
    pCta('Échanger sur ma situation','/contact'),
 ]],
 'a-propos'=>['title'=>'À propos de Prestadmin','slug'=>'a-propos','blocks'=>[
    pTextBlock($about),
    pKeyValues('Une professionnelle de proximité',[
        ['label'=>'Expérience','value'=>'8 ans dans la gestion administrative'],
        ['label'=>'Implantation','value'=>'Metz, Moselle'],
        ['label'=>'Interventions','value'=>'Sur site ou à distance selon la mission'],
    ]),
    pCta('Contacter Prestadmin','/contact'),
 ]],
 'offres'=>['title'=>'Offres et prestations','slug'=>'offres','blocks'=>[
    pTextBlock($serviceIntro),
    pCards('Prestations',$offers),
    pTextBlock('Pour la préparation complète d’une liste de 40 clients maximum, le forfait facturation électronique est de 39 €. Les autres prestations font l’objet d’un périmètre convenu avec vous.'),
    pCta('Consulter la prestation facturation électronique','/facturation-electronique'),
    pCta('Décrire mon besoin','https://www.prestadmin57.fr/projet/'),
 ]],
 'ressources'=>['title'=>'Ressources et outils','slug'=>'ressources','blocks'=>[
    pTextBlock('Retrouvez les outils utiles pour vérifier les informations de vos clients et préparer vos demandes. Les services interactifs restent accessibles sur le site Prestadmin57 actuel pendant la migration.'),
    pCards('Outils disponibles',[
        ['title'=>'Rechercher une entreprise','text'=>'Retrouvez les données administratives publiques d’une entreprise.', 'url'=>'https://www.prestadmin57.fr/outils/siret/'],
        ['title'=>'Préparer votre besoin','text'=>'Répondez aux questions de préparation et choisissez comment contacter Prestadmin.', 'url'=>'https://www.prestadmin57.fr/projet/'],
        ['title'=>'Préparer la facturation électronique','text'=>'Consultez les forfaits et les étapes de préparation de votre liste clients.', 'url'=>'/facturation-electronique'],
        ['title'=>'Diagnostic RGPD','text'=>'Faites le point sur les documents, procédures, site web et traceurs.', 'url'=>'https://rgpd.prestadmin57.fr/'],
    ]),
    pCta('Contacter Prestadmin','/contact'),
 ]],
 'contact'=>['title'=>'Contacter Prestadmin','slug'=>'contact','blocks'=>[
    pTextBlock('Vous ne savez pas quelle prestation choisir ? Décrivez simplement votre situation. Prestadmin intervient à Metz, en Moselle et à distance selon la mission.'),
    pKeyValues('Coordonnées et horaires',$contactInfo),
    pCta('Envoyer un e-mail à Prestadmin','mailto:contact@prestadmin57.fr'),
    ['type'=>'contact_form','heading'=>'Votre message'],
 ]],
 'facturation-electronique'=>['title'=>'Facturation électronique : votre liste clients vérifiée et complétée','slug'=>'facturation-electronique','blocks'=>[
    pTextBlock($factIntro),
    ['type'=>'pricing','heading'=>'Commencer gratuitement ou aller plus loin','items'=>$pricing],
    pTextBlock('La prestation à 39 € comprend la reprise de votre fichier et un contrôle humain des correspondances incertaines ; elle ne se limite pas à 40 recherches. Le forfait couvre jusqu’à 40 clients, avec 19,50 € d’acompte puis 19,50 € lorsque le dossier est prêt. Au-delà, la prestation est proposée sur devis.'),
    ...($process!==[]?[pSteps('Du premier fichier au dossier final',$process)]:[]),
    pCards('Ce que vous préparez, ce que Prestadmin prend en charge',$factGuide),
    pCta('Voir les recherches et les commandes sur le site actuel','https://www.prestadmin57.fr/facturation-electronique/'),
 ]],
 'carte'=>['title'=>'Prestadmin — Carte de présentation','slug'=>'carte','blocks'=>[
    pTextBlock($cardIntro),
    pKeyValues('Votre interlocutrice',[
        ['label'=>'Camille Poirel','value'=>'Assistante administrative indépendante · 8 ans d’expérience dans la gestion administrative'],
        ['label'=>'Téléphone','value'=>'07 49 63 62 34'],
        ['label'=>'E-mail','value'=>'contact@prestadmin57.fr'],
        ['label'=>'Localisation','value'=>'Basée à Metz · interventions en Moselle ou à distance selon la mission'],
    ]),
    pCards('Une aide adaptée à votre situation',$offers),
    pCta('Enregistrer les coordonnées','https://www.prestadmin57.fr/carte/camille-poirel.vcf'),
    pCta('Préparer ma demande','https://www.prestadmin57.fr/projet/'),
 ]],
 'mentions-prestadmin'=>['title'=>'Mentions légales — contenu du site d’origine, à valider','slug'=>'mentions-prestadmin','blocks'=>[
    pTextBlock($legalText),
 ]],
 'confidentialite-prestadmin'=>['title'=>'Confidentialité — contenu du site d’origine, à adapter','slug'=>'confidentialite-prestadmin','blocks'=>[
    pTextBlock('Contenu du site d’origine : à adapter après vérification des traitements réellement actifs dans Dendrila, notamment du formulaire de contact.'),
    pTextBlock($privacyText),
 ]],
];
$pdo=Application::database();
$store=new PdoCmsStore($pdo);$actor=new ActorContext('default','owner');
$authz=new AuthorizationService($store);$content=new ContentService($store,$authz);
$pageDef=$store->findContentType('default','page');
if(!is_array($pageDef)) throw new RuntimeException('Site page type missing');
$definition=new ContentTypeDefinition($pageDef);
foreach($values as $slug=>$page){
    try{$definition->validateValues($page);}
    catch(Throwable $e){
        echo "INVALID_PAGE=$slug ".count($page['blocks'])." blocks\n";
        foreach($page['blocks'] as $index=>$block){
            try{$definition->validateValues(['title'=>$page['title'],'slug'=>$slug,'blocks'=>[$block]]);}
            catch(Throwable $other){echo "INVALID_BLOCK $index ".($block['type']??'unknown')." ".json_encode($block,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
        }
        throw $e;
    }
}
$existing=$content->list($actor,['type'=>'page','limit'=>100]);
$bySlug=[];foreach($existing as $item)$bySlug[$item['slug']]=$item;
echo ($apply?'APPLY':'DRY_RUN')." PRESTADMIN_EDITORIAL_IMPORT\n";
foreach($values as $slug=>$page){
    $record=$bySlug[$slug]??null;
    if($record && $record['values']===$page){echo "SKIP $slug unchanged\n";continue;}
    if($record && in_array($slug,['home','services','methode','a-propos','offres','ressources','contact'],true)
        && (int)$record['version']>2) throw new RuntimeException('Page has been edited: '.$slug.'; refusing overwrite.');
    if($record && !in_array($slug,['home','services','methode','a-propos','offres','ressources','contact'],true))
        throw new RuntimeException('Unexpected content exists for '.$slug);
    echo ($record?'UPDATE ':'CREATE ').$slug.' blocks='.count($page['blocks'])."\n";
}
if(!$apply){echo "Use --apply on this staging site to import.\n";exit(0);}
$backupRoot=dirname(__DIR__).'/../../backups';
if(!is_dir($backupRoot))mkdir($backupRoot,0700,true);
$backup=$backupRoot.'/prestadmin-dendrila-before-content-import-'.gmdate('Ymd-His').'.sqlite';
$pdo->exec('VACUUM INTO '.$pdo->quote($backup));
chmod($backup,0600);
echo 'PRIVATE_SQLITE_BACKUP='.basename($backup)."\n";
foreach($values as $slug=>$page){
    $record=$bySlug[$slug]??null;
    if($record && $record['values']===$page){continue;}
    if($record){
        $res=$content->updateDraft($actor,$record['content_id'],(int)$record['version'],$page,'prestadmin-import-v1-update-'.$slug);
    }else{
        $res=$content->createDraft($actor,'page',$page,'prestadmin-import-v1-create-'.$slug);
    }
    // Publish only public marketing pages. Legal texts remain drafts for
    // review, as privacy terms on staging may differ from the old site.
    if(!in_array($slug,['mentions-prestadmin','confidentialite-prestadmin'],true)){
        $fresh=$res['content'];
        if($fresh['status']!=='published'){
            $content->transition($actor,$fresh['id'],'published',$fresh['version'],'prestadmin-import-v1-publish-'.$slug);
        }
    }
}
$nav=new NavigationService(new PdoNavigationRepository($pdo),$store,$authz);
$main=$nav->get($actor,'main');
$links=[
    ['id'=>'home','label'=>'Accueil','url'=>'/','visible'=>true],
    ['id'=>'services','label'=>'Prestations','url'=>'/services','visible'=>true],
    ['id'=>'facturation','label'=>'Facturation électronique','url'=>'/facturation-electronique','visible'=>true],
    ['id'=>'methode','label'=>'Méthode','url'=>'/methode','visible'=>true],
    ['id'=>'a-propos','label'=>'À propos','url'=>'/a-propos','visible'=>true],
    ['id'=>'carte','label'=>'Carte','url'=>'/carte','visible'=>true],
    ['id'=>'ressources','label'=>'Outils','url'=>'/ressources','visible'=>true],
    ['id'=>'contact','label'=>'Contact','url'=>'/contact','visible'=>true],
    ['id'=>'projet','label'=>'Préparer mon besoin','url'=>'https://www.prestadmin57.fr/projet/','visible'=>true],
];
if($main['items']!==$links){
    $nav->save($actor,'main','Menu Prestadmin','primary',$links,(int)$main['version']);
}
echo "STAGING_CONTENT_IMPORT_DONE\n";
