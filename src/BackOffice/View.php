<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;

/** Rendu partagé, sans HTML arbitraire injecté par un thème ou un client. */
final readonly class View
{
    public function __construct(
        private Config $config,
        private string $csrf,
        private CoreData $core,
        private ?MediaPort $media = null,
        private ?UsersPort $users = null,
    ) {}

    private static function h(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
    private function link(string $section,array $params=[]): string
    {
        return self::h($this->config->url($section,$params));
    }

    public static function unauthorized(Config $config): string
    {
        $title=self::h($config->siteName);
        $login=self::h($config->loginUrl);
        return '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="robots" content="noindex"><title>Connexion</title>'
            .'<body><main><h1>'.$title.'</h1><p>Connectez-vous à votre espace sécurisé pour accéder au site.</p>'
            .'<p><a href="'.$login.'">Se connecter</a></p></main></body></html>';
    }
    public static function error(Config $config,string $message): string
    {
        return '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="robots" content="noindex"><title>Erreur</title>'
            .'<body><main><p>'.self::h($message).'</p><p><a href="'.self::h($config->basePath).'">Retour à l’administration</a></p></main></body></html>';
    }

    /**
     * @param list<array<string,mixed>> $pages
     * @param array<string,mixed>|null $selectedPage
     * @param list<array<string,mixed>> $menus
     * @param array<string,mixed>|null $selectedMenu
     * @param array<string,mixed> $query
     */
    public function render(ActorContext $actor,string $section,array $pages,?array $selectedPage,
        array $menus,?array $selectedMenu,array $query): string
    {
        $links=[
            'tableau'=>'Vue d’ensemble',
            'textes'=>'Textes',
            'menu'=>'Menu public',
            'medias'=>'Photos',
            'utilisateurs'=>'Utilisateurs',
            'appli'=>'Appli',
        ];
        $title=$links[$section]??'Vue d’ensemble';
        ob_start(); ?>
<!doctype html>
<html lang="fr">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="robots" content="noindex,nofollow,noarchive">
 <meta name="theme-color" content="<?= self::h($this->config->primaryColor) ?>">
 <meta name="apple-mobile-web-app-capable" content="yes">
 <meta name="apple-mobile-web-app-title" content="<?= self::h($this->config->siteName) ?>">
 <link rel="apple-touch-icon" href="<?= self::h($this->config->iconUrl) ?>">
 <link rel="manifest" href="<?= self::h($this->config->basePath) ?>?manifest=1">
 <link rel="stylesheet" href="<?= self::h($this->config->assetsPath) ?>/backoffice.css">
 <link rel="stylesheet" href="<?= self::h($this->config->basePath) ?>?theme=1">
 <link rel="stylesheet" href="/assets/install.css">
 <title><?= self::h($title) ?> — <?= self::h($this->config->siteName) ?></title>
</head>
<body>
<a class="skip" href="#main">Aller au contenu</a>
<header class="masthead">
 <div class="masthead-inner">
  <div><span class="eyebrow">Dendrila · Administration</span><div class="site-name"><?= self::h($this->config->siteName) ?></div></div>
  <a class="back-link" href="/">Voir le site</a>
  <?php if(getenv('DENDRILA_SITE_ID')===$actor->siteId): ?>
  <a href="/admin/accounts.php">Comptes</a>
  <?php if(in_array('forum',explode(',',getenv('DENDRILA_ADDONS')?:''),true)): ?><a href="/forum/">Forum</a><?php endif ?>
  <form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>"><button class="button secondary">Déconnexion</button></form>
  <?php endif ?>
 </div>
</header>
<div class="workspace">
 <nav class="admin-nav" aria-label="Administration du site">
  <?php foreach($links as $id=>$label): ?>
   <?php if($id==='medias' && (!$this->media || !$this->core->can($actor,'media.read')))continue; ?>
   <?php if($id==='utilisateurs' && (!$this->users || !$this->core->can($actor,'users.read')))continue; ?>
   <?php if($id==='menu' && !$this->core->can($actor,'navigation.read'))continue; ?>
   <?php if($id==='textes' && !$this->core->can($actor,'content.read'))continue; ?>
   <a href="<?= $this->link($id) ?>"<?= $id===$section?' aria-current="page"':'' ?>><?= self::h($label) ?></a>
  <?php endforeach ?>
 </nav>
 <main id="main" class="admin-main">
 <?php if(($query['saved']??null)==='1'): ?><p role="status" class="notice">Modifications enregistrées.</p><?php endif ?>
 <?php if($section==='tableau'): ?>
  <?= $this->dashboard($actor,$pages,$menus) ?>
 <?php elseif($section==='textes'): ?>
  <?= $this->texts($actor,$pages,$selectedPage,$query) ?>
 <?php elseif($section==='menu'): ?>
  <?= $this->navigation($actor,$menus,$selectedMenu) ?>
 <?php elseif($section==='medias'): ?>
  <?= $this->media($actor) ?>
 <?php elseif($section==='utilisateurs'): ?>
  <?= $this->members($actor) ?>
 <?php elseif($section==='appli'): ?>
  <?= $this->app() ?>
 <?php endif ?>
 </main>
</div>
<script defer src="<?= self::h($this->config->assetsPath) ?>/backoffice.js"></script>
</body>
</html>
<?php
        return (string)ob_get_clean();
    }

    /** @param list<array<string,mixed>> $pages @param list<array<string,mixed>> $menus */
    private function dashboard(ActorContext $actor,array $pages,array $menus): string
    {
        ob_start(); ?>
  <p class="eyebrow">Bienvenue</p><h1>Gestion du site</h1>
  <p class="intro">Retrouvez les outils de publication, sans réglages techniques inutiles.</p>
  <div class="metric-grid">
   <div class="metric"><span>Pages accessibles</span><strong><?=count($pages)?></strong></div>
   <div class="metric"><span>Menus</span><strong><?=count($menus)?></strong></div>
   <div class="metric"><span>Projet</span><strong><?=self::h($this->config->siteName)?></strong></div>
  </div>
  <div class="panel"><h2>Que souhaitez-vous modifier ?</h2>
   <div class="action-grid">
    <?php if($this->core->can($actor,'content.read')): ?><a class="action-tile" href="<?=$this->link('textes')?>"><strong>Textes</strong><span>Pages et contenus éditoriaux</span></a><?php endif ?>
    <?php if($this->core->can($actor,'navigation.read')): ?><a class="action-tile" href="<?=$this->link('menu')?>"><strong>Menu</strong><span>Ordre et visibilité</span></a><?php endif ?>
    <?php if($this->media && $this->core->can($actor,'media.read')): ?><a class="action-tile" href="<?=$this->link('medias')?>"><strong>Photos</strong><span>Photothèque du site</span></a><?php endif ?>
    <a class="action-tile" href="<?=$this->link('appli')?>"><strong>Appli</strong><span>Android et iPhone</span></a>
   </div>
  </div>
<?php return (string)ob_get_clean();
    }

    /** @param list<array<string,mixed>> $pages @param array<string,mixed>|null $page */
    private function texts(ActorContext $actor,array $pages,?array $page,array $query=[]): string
    {
        if(!$this->core->can($actor,'content.read')) return '<p class="notice">Accès réservé aux éditeurs.</p>';
        $canEdit=($this->core->can($actor,'content.update')||($this->core->can($actor,'content.update_own')&&($page['created_by_id']??null)===$actor->actorId))&&($page['status']??'')!=='archived';
        $fields=$this->core->textFields($actor);
        ob_start();?>
 <p class="eyebrow">Édition</p><h1>Modifier les textes</h1>
 <p class="intro">Choisissez une page : son éditeur s’ouvre automatiquement.</p>
 <?php if($this->core->can($actor,'content.create')): ?>
 <details class="panel" data-create-page>
  <summary class="new-page-summary">+ Nouvelle page</summary>
  <form method="post" action="<?=self::h($this->config->basePath)?>">
   <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
   <input type="hidden" name="action" value="create_page">
   <div class="field"><label for="create-template">Type de page</label>
    <select id="create-template" name="template" required>
     <option value="presentation">Présentation classique</option>
     <?php if($this->media): ?><option value="gallery">Galerie photos</option><?php endif ?>
     <option value="free">Autre : page libre</option>
    </select>
   </div>
   <div class="field"><label for="create-title">Titre</label>
    <input id="create-title" name="title" maxlength="240" required>
   </div>
   <div class="field"><label for="create-slug">Adresse courte</label>
    <input id="create-slug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="exemple-de-page" required>
    <small>Minuscules, chiffres et tirets uniquement. L’adresse ne doit pas être déjà utilisée.</small>
   </div>
   <button class="button" type="submit">Créer le brouillon</button>
   <p class="quiet">La page reste privée jusqu’à sa publication. La galerie sera remplie depuis la photothèque du site.</p>
  </form>
 </details>
 <?php endif ?>
 <?php if(($query['created']??null)==='1'): ?><p class="notice" role="status">Nouvelle page créée en brouillon. Vous pouvez maintenant la modifier.</p><?php endif ?>
 <div class="panel">
  <form class="chooser" method="get" action="<?=self::h($this->config->basePath)?>" data-page-chooser>
   <input type="hidden" name="section" value="textes">
   <label for="edit-page">Page à modifier</label>
   <select id="edit-page" name="page">
    <?php foreach($pages as $row):
        $id=(string)$row['content_id'];
        $caption=(string)($row['values']['title']??$row['slug']??'Page');
        if($row['slug']==='home')$caption='Page d’accueil';
    ?>
     <option value="<?=self::h($id)?>" <?=$page && $id===$page['content_id']?'selected':''?>><?=self::h($caption)?></option>
    <?php endforeach ?>
   </select>
   <noscript><button class="button">Afficher la page</button></noscript>
  </form>
  <?php if($page===null): ?>
   <p class="quiet">Aucune page disponible pour cet utilisateur.</p>
  <?php else: ?>
   <div class="editor-divider"></div>
   <form method="post" action="<?=self::h($this->config->basePath)?>" data-page-editor>
    <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
    <input type="hidden" name="action" value="save_page">
    <input type="hidden" name="page_id" value="<?=self::h($page['content_id'])?>">
    <input type="hidden" name="version" value="<?=self::h($page['version'])?>">
    <?php foreach($fields as $field):
      $key=$field['id'];
      $value=$page['values'][$key]??'';
      if(!is_string($value))continue;
    ?>
     <div class="field">
      <label for="field-<?=self::h($key)?>"><?=self::h($field['label'])?></label>
      <textarea id="field-<?=self::h($key)?>" name="fields[<?=self::h($key)?>]" rows="<?=$key==='title'?3:7?>" maxlength="100000"
       <?=($field['required']?'required':'')?> <?=!$canEdit?'readonly':''?>><?=self::h($value)?></textarea>
      <?php if($key==='title'): ?><small>Les retours à la ligne sont conservés.</small><?php endif ?>
     </div>
    <?php endforeach ?>
    <?php if(count($fields)===0): ?><p class="quiet">Cette page possède des champs structurés qui nécessitent un éditeur de blocs.</p><?php endif ?>
    <?php if($canEdit && $fields!==[]): ?><button class="button" type="submit">Enregistrer le brouillon</button><?php endif ?>
   </form>
   <?= $this->blocks($actor,$page) ?>
   <?php if($this->core->can($actor,'content.publish') && in_array($page['status'],['draft','review'],true)): ?>
    <form method="post" action="<?=self::h($this->config->basePath)?>" class="publish-form" data-publish-form>
     <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
     <input type="hidden" name="action" value="publish_page">
     <input type="hidden" name="page_id" value="<?=self::h($page['content_id'])?>">
     <input type="hidden" name="version" value="<?=self::h($page['version'])?>">
     <button class="button secondary" type="submit">Publier le brouillon enregistré</button>
    </form>
   <?php endif ?>
  <?php endif ?>
 </div>
<?php return (string)ob_get_clean();
    }

    private function blocks(ActorContext $actor,array $page):string
    {
        $can=($page['status']??'')!=='archived'&&($this->core->can($actor,'content.update')||($this->core->can($actor,'content.update_own')&&($page['created_by_id']??null)===$actor->actorId));
        $media=$this->media&&$this->core->can($actor,'media.read')?$this->media->list($actor):[];
        $hidden='<input type="hidden" name="csrf" value="'.self::h($this->csrf).'"><input type="hidden" name="page_id" value="'.self::h($page['content_id']).'"><input type="hidden" name="version" value="'.self::h($page['version']).'">';
        $html=(!$this->media?'<p class="notice">La médiathèque est désactivée sur ce site : les pages et blocs galerie ne sont pas disponibles.</p>':'').'<h2>Contenu de la page</h2><p>Enregistrez les blocs avant de les déplacer. La publication reste une action séparée.</p>';
        foreach($page['values']['blocks']??[] as $i=>$block){
            $type=$block['type'];$html.='<section class="block-editor"><h3>Bloc '.($i+1).' — '.self::h(['text'=>'Texte','rich_text'=>'Texte','image'=>'Photo','gallery'=>'Galerie','cta'=>'Bouton','faq'=>'Questions et réponses','cards'=>'Cartes','key_values'=>'Informations','statistics'=>'Chiffres','embed_safe'=>'Contenu externe'][$type]??$type).'</h3>';
            $html.='<form method="post" action="'.self::h($this->config->basePath).'">'.$hidden.'<input type="hidden" name="action" value="save_blocks"><fieldset'.(!$can?' disabled':'').'>';
            foreach(BlockEditor::FIELDS[$type]??[] as $key=>$label){
                $name='blocks['.$i.']['.$key.']';$value=$block[$key]??'';
                $html.='<label>'.self::h($label);
                if($key==='provider'){$html.='<select name="'.$name.'">';foreach(['youtube-nocookie'=>'YouTube sans cookies','vimeo'=>'Vimeo','openstreetmap'=>'OpenStreetMap'] as $provider=>$caption)$html.='<option value="'.$provider.'"'.($value===$provider?' selected':'').'>'.$caption.'</option>';$html.='</select><small>Adresse d’intégration HTTPS du fournisseur choisi. Chargement après consentement.</small>';}
                elseif($key==='media_id'){$html.='<select name="'.$name.'">';foreach($media as $photo)$html.='<option value="'.self::h($photo['id']).'"'.($value===$photo['id']?' selected':'').'>'.self::h($photo['title']).'</option>';$html.='</select>';}
                else $html.='<textarea name="'.$name.'" rows="'.($key==='text'?7:2).'">'.self::h($value).'</textarea>';
                $html.='</label>';
            }
            if($type==='gallery'){
                $html.='<input type="hidden" name="blocks['.$i.'][media_ids][]" value="">';
                foreach($media as $photo)$html.='<label><input type="checkbox" name="blocks['.$i.'][media_ids][]" value="'.self::h($photo['id']).'"'.(in_array($photo['id'],$block['media_ids'],true)?' checked':'').'> '.self::h($photo['title']).'</label>';
                if($media===[])$html.='<p>Ajoutez des photos à la photothèque pour remplir cette galerie.</p>';
            }
            foreach($block['items']??[] as $j=>$item){$html.='<fieldset><legend>Élément '.($j+1).'</legend>';foreach(BlockEditor::ITEMS[$type]??[] as $key=>$label)$html.='<label>'.self::h($label).'<textarea name="blocks['.$i.'][items]['.$j.']['.$key.']">'.self::h($item[$key]??'').'</textarea></label>';$html.='</fieldset>';}
            if($can)$html.='<button class="button">Enregistrer ce bloc</button>';
            $html.='</fieldset></form>';
            if($can)foreach($block['items']??[] as $j=>$item){
                $html.='<form method="post" action="'.self::h($this->config->basePath).'" class="block-actions">'.$hidden.'<input type="hidden" name="action" value="change_block"><input type="hidden" name="index" value="'.$i.'"><input type="hidden" name="item_index" value="'.$j.'"><span>Élément '.($j+1).'</span>';
                if($j>0)$html.='<button class="button secondary" name="operation" value="item_up">Monter l’élément</button>';
                if($j<count($block['items'])-1)$html.='<button class="button secondary" name="operation" value="item_down">Descendre l’élément</button>';
                $html.='<button class="button secondary" name="operation" value="remove_item">Retirer l’élément '.($j+1).'</button></form>';
            }
            if($can){$html.='<form method="post" action="'.self::h($this->config->basePath).'" class="block-actions">'.$hidden.'<input type="hidden" name="action" value="change_block"><input type="hidden" name="index" value="'.$i.'">';
                if($i>0)$html.='<button class="button secondary" name="operation" value="up">Monter</button>';
                if($i<count($page['values']['blocks'])-1)$html.='<button class="button secondary" name="operation" value="down">Descendre</button>';
                if(isset(BlockEditor::ITEMS[$type]))$html.='<button class="button secondary" name="operation" value="add_item">Ajouter un élément</button>';
                $html.='<button class="button secondary" name="operation" value="remove">Retirer le bloc du brouillon</button></form>';
            }
            $html.='</section>';
        }
        if($can){$html.='<form method="post" action="'.self::h($this->config->basePath).'">'.$hidden.'<input type="hidden" name="action" value="change_block"><input type="hidden" name="operation" value="add"><label>Ajouter un bloc<select name="type">';
            foreach(['text'=>'Texte','rich_text'=>'Texte avec retours à la ligne','embed_safe'=>'Contenu externe avec consentement','cta'=>'Bouton','cards'=>'Cartes','faq'=>'Questions et réponses','key_values'=>'Informations','statistics'=>'Chiffres'] as $key=>$label)$html.='<option value="'.$key.'">'.$label.'</option>';
            if($this->media)$html.='<option value="gallery">Galerie</option>';
            if($media!==[])$html.='<option value="image">Photo</option>';
            $html.='</select></label>';if($media!==[]){$html.='<label>Photo (pour un bloc photo)<select name="media_id">';foreach($media as $photo)$html.='<option value="'.self::h($photo['id']).'">'.self::h($photo['title']).'</option>';$html.='</select></label>';}
            $html.='<button class="button secondary">Ajouter le bloc</button></form>';
        }
        if(getenv('DENDRILA_SITE_ID')===$actor->siteId&&$this->core->can($actor,'content.preview'))$html.='<p><a class="button secondary" href="/admin/preview.php?id='.rawurlencode($page['content_id']).'" target="_blank" rel="noopener">Prévisualiser le brouillon</a></p>';
        $targets=[];
        if($page['status']==='archived'&&$this->core->can($actor,'content.update'))$targets['draft']='Restaurer en brouillon';
        if($page['status']==='draft'&&$this->core->can($actor,'content.submit_for_review'))$targets['review']='Soumettre pour validation';
        if($page['status']==='published'&&$this->core->can($actor,'content.unpublish'))$targets['draft']='Retirer du site public';
        if($page['status']!=='archived'&&$this->core->can($actor,'content.archive'))$targets['archived']='Archiver la page';
        foreach($targets as $target=>$label)$html.='<form method="post" action="'.self::h($this->config->basePath).'" class="publish-form">'.$hidden.'<input type="hidden" name="action" value="transition_page"><button class="button secondary" name="target" value="'.$target.'">'.$label.'</button></form>';
        return $html;
    }

    /** @param list<array<string,mixed>> $menus @param array<string,mixed>|null $menu */
    private function navigation(ActorContext $actor,array $menus,?array $menu): string
    {
        if(!$this->core->can($actor,'navigation.read'))return '<p class="notice">Accès non autorisé.</p>';
        $canEdit=$this->core->can($actor,'navigation.manage');
        ob_start();?>
 <p class="eyebrow">Navigation</p><h1>Organiser le menu</h1>
 <p class="intro">Déplacez les pages par la poignée. Aucun numéro de position à saisir.</p>
 <div class="panel">
 <?php if($menu===null): ?><p class="quiet">Aucun menu configuré pour ce site.</p>
 <?php else: ?>
  <?php if(count($menus)>1): ?>
   <form class="chooser" method="get" action="<?=self::h($this->config->basePath)?>">
    <input type="hidden" name="section" value="menu">
    <label for="menu-choice">Menu à modifier</label>
    <select id="menu-choice" name="menu">
     <?php foreach($menus as $option): ?><option value="<?=self::h($option['menu_id'])?>" <?=$option['menu_id']===$menu['menu_id']?'selected':''?>><?=self::h($option['label'])?></option><?php endforeach ?>
    </select>
    <noscript><button class="button">Afficher le menu</button></noscript>
   </form>
  <?php endif ?>
  <form method="post" action="<?=self::h($this->config->basePath)?>" data-menu-form>
   <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
   <input type="hidden" name="action" value="save_menu">
   <input type="hidden" name="menu_id" value="<?=self::h($menu['menu_id'])?>">
   <input type="hidden" name="version" value="<?=self::h($menu['version'])?>">
   <ul class="sortable" data-sortable aria-label="Ordre du menu">
    <?php foreach($menu['items'] as $item): ?>
     <li class="sort-item" data-sort-item>
      <input type="hidden" name="order[]" value="<?=self::h($item['id'])?>">
      <div class="sort-line">
       <?php if($canEdit): ?><button class="handle" data-sort-handle type="button" aria-label="Déplacer <?=self::h($item['label'])?> avec les flèches ou le doigt">⋮⋮</button><?php endif ?>
       <div class="sort-title"><strong><?=self::h($item['label'])?></strong><?php if(!$item['visible']): ?><small>Masqué</small><?php endif ?></div>
       <?php if($canEdit): ?><button class="edit-toggle" type="button" data-toggle-options aria-expanded="false">Modifier</button><?php endif ?>
      </div>
      <?php if($canEdit): ?>
       <div class="sort-options" hidden>
        <label>Nom affiché<input name="labels[<?=self::h($item['id'])?>]" maxlength="190" required value="<?=self::h($item['label'])?>"></label>
        <label class="checkbox"><input name="shown[<?=self::h($item['id'])?>]" type="checkbox" value="1" <?=$item['visible']?'checked':''?> <?=($item['url']==='/')?'disabled':''?>> Afficher cette page</label>
        <?php if($item['url']==='/'): ?><small>La page d’accueil reste toujours visible.</small><?php endif ?>
       </div>
      <?php endif ?>
     </li>
    <?php endforeach ?>
   </ul>
   <p role="status" aria-live="polite" data-sort-announcement></p>
   <?php if($canEdit): ?><button class="button">Enregistrer le menu</button><?php endif ?>
  </form>
  <?php if($canEdit): ?>
  <form method="post" action="<?=self::h($this->config->basePath)?>" class="publish-form">
   <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>"><input type="hidden" name="action" value="add_page_to_menu">
   <input type="hidden" name="menu_id" value="<?=self::h($menu['menu_id'])?>"><input type="hidden" name="version" value="<?=self::h($menu['version'])?>">
   <label>Ajouter une page<select name="page_id"><?php foreach($this->core->pages($actor) as $p): ?><option value="<?=self::h($p['content_id'])?>"><?=self::h($p['values']['title'])?></option><?php endforeach ?></select></label><button class="button secondary">Ajouter au menu</button>
  </form>
  <?php endif ?>
 <?php endif ?>
 </div>
<?php return (string)ob_get_clean();
    }

    private function media(ActorContext $actor): string
    {
        if(!$this->media || !$this->core->can($actor,'media.read'))return '<p class="notice">Photothèque non raccordée sur ce site.</p>';
        $rows=$this->media->list($actor);
        ob_start();?>
 <p class="eyebrow">Photothèque</p><h1>Gérer les photos</h1>
 <p class="intro">Retrouvez vos photographies sans manipuler leur URL technique.</p>
 <div class="panel">
  <?php if($this->core->can($actor,'media.upload')): ?>
   <form method="post" enctype="multipart/form-data" action="<?=self::h($this->config->basePath)?>">
    <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
    <input type="hidden" name="action" value="upload_media">
    <div class="field"><label for="media-title">Titre</label><input id="media-title" name="title" maxlength="200" required></div>
    <div class="field"><label for="media-alt">Description accessible</label><input id="media-alt" name="alt" maxlength="500" required></div>
    <div class="field"><label for="media-file">Choisir une photo</label><input id="media-file" type="file" name="image" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG ou WebP — 6 Mo maximum.</small></div>
    <button class="button">Ajouter la photographie</button>
   </form>
  <?php endif ?>
  <div class="media-grid">
   <?php foreach($rows as $row):
    if(!is_array($row)||!Config::safeUrl((string)($row['preview_url']??'')))continue;
    ?>
    <figure><img loading="lazy" src="<?=self::h($row['preview_url'])?>" alt="<?=self::h($row['alt']??'')?>"><figcaption><?=self::h($row['title']??'Photographie')?></figcaption></figure>
   <?php endforeach ?>
  </div>
  <?php if($rows===[]): ?><p class="quiet">Aucune photo disponible.</p><?php endif ?>
 </div>
<?php return (string)ob_get_clean();
    }

    private function members(ActorContext $actor): string
    {
        if(!$this->users || !$this->core->can($actor,'users.read'))return '<p class="notice">Comptes non raccordés sur ce site.</p>';
        $rows=$this->users->list($actor);
        ob_start();?>
 <p class="eyebrow">Accès</p><h1>Utilisateurs</h1>
 <p class="intro">Les invitations utilisent la connexion sécurisée du site, sans transmettre de mot de passe.</p>
 <div class="panel">
  <div class="table-overflow"><table><thead><tr><th>Compte</th><th>Rôle</th></tr></thead><tbody>
  <?php foreach($rows as $row): ?><tr><td><?=self::h($row['label'])?></td><td><?=self::h($row['role'])?></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php if($this->core->can($actor,'users.manage')): ?>
   <form method="post" action="<?=self::h($this->config->basePath)?>">
    <input type="hidden" name="csrf" value="<?=self::h($this->csrf)?>">
    <input type="hidden" name="action" value="invite_user">
    <div class="field"><label for="new-email">Inviter par e-mail</label><input type="email" id="new-email" name="email" maxlength="254" required></div>
    <div class="field"><label for="role">Rôle</label><select id="role" name="role"><option value="editor">Éditeur</option><option value="admin">Administrateur</option><option value="contributor">Contributeur</option><option value="viewer">Lecteur</option></select></div>
    <button class="button">Envoyer l’invitation</button>
   </form>
  <?php endif ?>
 </div>
<?php return (string)ob_get_clean();
    }

    private function app(): string
    {
        ob_start();?>
 <p class="eyebrow">Accès mobile</p><h1>Appli</h1>
 <div class="panel">
  <h2>Sur iPhone ou iPad</h2>
  <p>Ouvrez cette administration dans <strong>Safari</strong>, puis :</p>
  <ol class="steps">
   <li>Touchez <strong>Partager</strong>.</li>
   <li>Choisissez <strong>Sur l’écran d’accueil</strong>.</li>
   <li>Activez <strong>Ouvrir comme app web</strong> si proposé, puis touchez <strong>Ajouter</strong>.</li>
  </ol>
  <p class="quiet">Il s’agit du site sécurisé installé sur votre écran d’accueil, pas d’une application iOS native. Une connexion reste nécessaire.</p>
 </div>
 <div class="panel">
  <h2>Sur Android</h2>
  <?php if($this->config->androidDownloadUrl!==null): ?>
   <p><a class="button" href="<?=self::h($this->config->androidDownloadUrl)?>" rel="noopener noreferrer">Télécharger Mon Manager Web</a></p>
   <p class="quiet">Vérifiez la version et les consignes de sécurité indiquées sur la page de téléchargement.</p>
  <?php else: ?>
   <p class="quiet">Le téléchargement Android n’a pas encore été configuré pour ce site.</p>
  <?php endif ?>
 </div>
<?php return (string)ob_get_clean();
    }
}
