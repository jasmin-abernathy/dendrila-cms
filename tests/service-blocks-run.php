<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Dendrila\Cms\Content\ContentTypeDefinition;
use Dendrila\Cms\BackOffice\BlockEditor;
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
function assertCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$schema = json_decode((string)file_get_contents(dirname(__DIR__).'/examples/content-types/page.json'), true, 512, JSON_THROW_ON_ERROR);
$type = new ContentTypeDefinition($schema);
$blocks = [
 ['type'=>'text','text'=>'Un suivi administratif clair et humain.'],
 ['type'=>'steps','heading'=>'Comment ça marche ?', 'items'=>[
  ['title'=>'Premier échange','text'=>'Vos besoins et vos priorités.'],
  ['title'=>'Choix de la prestation','text'=>'Une solution sans engagement excessif.'],
 ]],
 ['type'=>'pricing','heading'=>'Prestations','items'=>[
  ['title'=>'Vérification de base','price'=>'Gratuit','text'=>'5 recherches offertes.'],
  ['title'=>'Préparation du fichier','price'=>'39 €','text'=>'Jusqu’à 40 entreprises.','label'=>'En savoir plus','url'=>'/contact'],
 ]],
];
$type->validateValues(['title'=>'Prestations','slug'=>'prestations','blocks'=>$blocks]);
$editorSteps = BlockEditor::blank('steps');
$editorPricing = BlockEditor::blank('pricing');
assertCheck($editorSteps['items']===[] && $editorPricing['items']===[], 'Editable blank blocks are available');
$editorPricing = BlockEditor::collection($editorPricing,'add_item',0);
assertCheck(count($editorPricing['items'])===1, 'Editable collection add works');
$editorPricing = BlockEditor::update([$editorPricing],[0=>['items'=>[0=>[
 'title'=>'Offre ponctuelle','price'=>'39 €','text'=>'Sans abonnement','url'=>'/contact','label'=>'Consulter'
]]]],[])[0];
assertCheck($editorPricing['items'][0]['price']==='39 €', 'Price changes are stored');
$presentation=Presentation::forTheme('ultra-minimal');
$html=(new PublicPageRenderer())->render(['name'=>'Prestadmin','language'=>'fr','home_url'=>'/'],
 ['title'=>'Prestations','blocks'=>$blocks], $presentation, $presentation['css_url'], [],
 [['label'=>'Accueil','url'=>'/'],['label'=>'Contact','url'=>'/contact']]);
assertCheck(str_contains($html,'class="d-steps"') && str_contains($html,'class="d-pricing"'), 'Front displays new blocks');
assertCheck(str_contains($html,'39 €') && str_contains($html,'href="/contact"'), 'Prices and internal links work');
$unsafe=$blocks;
$unsafe[2]['items'][1]['url']='javascript:alert(1)';
try {$type->validateValues(['title'=>'Test','slug'=>'test','blocks'=>$unsafe]);throw new RuntimeException('Unsafe URL accepted');}
catch (InvalidArgumentException) {}
$unsafe=$blocks;
$unsafe[1]['items'][0]['text']='<script>alert(1)</script>';
$type->validateValues(['title'=>'Test','slug'=>'test','blocks'=>$unsafe]);
$escaped=(new PublicPageRenderer())->render(['name'=>'Prestadmin','language'=>'fr','home_url'=>'/'],
 ['title'=>'Test','blocks'=>$unsafe], $presentation, $presentation['css_url']);
assertCheck(!str_contains($escaped,'<script>alert(1)</script>'), 'HTML injection escaped');
assertCheck(str_contains($escaped,'&lt;script&gt;'), 'Escaped content preserved');

$linkedCards=[['type'=>'cards','heading'=>'Prestations','items'=>[
 ['title'=>'Un service','text'=>'Informations utiles','url'=>'https://example.org/projet/','label'=>'Préparer ma demande'],
 ['title'=>'Une explication','text'=>'Sans action']
]]];
$type->validateValues(['title'=>'Essai','slug'=>'test','blocks'=>$linkedCards]);
$cardHtml=(new PublicPageRenderer())->render(['name'=>'Site exemple'],
 ['title'=>'Essai','slug'=>'home','blocks'=>$linkedCards],$presentation,$presentation['css_url']);
assertCheck(str_contains($cardHtml,'class="d-card d-card--linked"') && str_contains($cardHtml,'Préparer ma demande'),
 'Clickable cards visibly show their native editable action label');
assertCheck(str_contains($cardHtml,'href="https://example.org/projet/"'),
 'Linked cards retain the intended destination');
assertCheck(substr_count($cardHtml,'class="d-card__action" aria-hidden="true"')===1,
 'Unlinked cards do not suggest an action');
assertCheck(str_contains($cardHtml,'class="d-card__action-arrow"'),
 'Action has a visible directional cue');
$edited=BlockEditor::update($linkedCards,[0=>['items'=>[0=>['label'=>'Voir les détails']]]],[])[0];
assertCheck($edited['items'][0]['label']==='Voir les détails','Editor can change card action text');
$tooLong=$linkedCards;$tooLong[0]['items'][0]['label']=str_repeat('x',301);
try{$type->validateValues(['title'=>'Test','slug'=>'test','blocks'=>$tooLong]);
 throw new RuntimeException('Oversized card label accepted');
}catch(InvalidArgumentException){}
echo "Reusable services blocks: schema, editor, renderer, safe URL and escaped text OK\n";

