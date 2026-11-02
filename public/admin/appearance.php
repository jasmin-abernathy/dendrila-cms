<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\{Application as App,AppearanceSettings};
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Runtime\{BrandImageStore,BrandFontStore};
use Dendrila\Cms\Http\UploadedFile;

App::session();
try {
    $pdo=App::database();
    $actor=App::identity($pdo)->currentActor();
    if(!$actor){header('Location: /admin/login.php',true,303);exit;}
    $store=new PdoCmsStore($pdo);
    if(!$store->actorHasPermission($actor->siteId,$actor->actorId,'users.manage')){
        http_response_code(403);exit('Réglages réservés aux administrateurs.');
    }
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' https: data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow');
    $csrf=new SessionCsrf();
    $message='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new \InvalidArgumentException('Formulaire expiré.');
            $previous=AppearanceSettings::read($pdo,App::site());
            $version=$_POST['version']??null;
            if(!is_string($version)||!hash_equals(hash('sha256',$previous['raw']),$version))
                throw new \DomainException('Ces réglages ont changé dans un autre onglet. Rechargez la page.');
            $theme=$_POST['theme']??null;
            if(!is_string($theme))throw new \InvalidArgumentException('Thème invalide.');
            $enabled=$_POST['enabled']??[];
            if(!is_array($enabled)||count($enabled)>count(AppearanceSettings::COLORS)
              ||count(array_filter($enabled,'is_string'))!==count($enabled)
              ||count(array_unique($enabled))!==count($enabled)
              ||array_diff($enabled,array_keys(AppearanceSettings::COLORS))!==[])
              throw new \InvalidArgumentException('Choix de couleurs invalide.');
            $brand=[];
            foreach(array_keys(AppearanceSettings::COLORS) as $key){
                if(!in_array($key,$enabled,true))continue;
                $value=$_POST['color'][$key]??null;
                if(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/D',$value))
                  throw new \InvalidArgumentException('Couleur invalide.');
                $brand[$key]=$value;
            }
            foreach(['heading_font','body_font'] as $key){
                $value=$_POST[$key]??'';
                if(!is_string($value) || !in_array($value,['', 'custom',...array_keys(AppearanceSettings::FONTS)],true))
                    throw new \InvalidArgumentException('Police invalide.');
                if($value==='custom'){
                    $fileKey=$key.'_file';
                    if(isset($previous['brand'][$fileKey]))$brand[$fileKey]=$previous['brand'][$fileKey];
                }elseif($value!=='')$brand[$key]=$value;
            }
            $assetRemoved=$_POST['remove_asset']??[];
            $fontRemoved=$_POST['remove_font']??[];
            foreach([[$assetRemoved,AppearanceSettings::ASSETS],[$fontRemoved,AppearanceSettings::CUSTOM_FONTS]] as [$selection,$known]){
                if(!is_array($selection)||count($selection)>count($known)
                    ||count(array_filter($selection,'is_string'))!==count($selection)
                    ||count(array_unique($selection))!==count($selection)
                    ||array_diff($selection,array_keys($known))!==[])
                    throw new \InvalidArgumentException('Sélection des fichiers invalide.');
            }
            foreach(AppearanceSettings::ASSETS as $key=>$label){
                if(!in_array($key,$assetRemoved,true)&&isset($previous['brand'][$key]))
                    $brand[$key]=$previous['brand'][$key];
            }
            foreach(AppearanceSettings::CUSTOM_FONTS as $key=>$label){
                if(in_array($key,$fontRemoved,true))unset($brand[$key]);
            }
            $uploads=[];
            foreach(['asset_file'=>AppearanceSettings::ASSETS,'font_file'=>AppearanceSettings::CUSTOM_FONTS] as $postKey=>$choices){
                $input=$_FILES[$postKey]??null;
                if($input!==null&&(!is_array($input)||!is_array($input['error']??null)
                    ||array_diff(array_keys($input['error']),array_keys($choices))!==[]))
                    throw new \InvalidArgumentException('Fichiers transmis invalides.');
                foreach($choices as $key=>$label){
                    $error=$input['error'][$key]??UPLOAD_ERR_NO_FILE;
                    if($error===UPLOAD_ERR_NO_FILE)continue;
                    if($error!==UPLOAD_ERR_OK)throw new \InvalidArgumentException(
                        'Impossible d’importer « '.$label.' » : fichier trop volumineux ou transfert interrompu.');
                    $uploads[]=[
                        'type'=>$postKey,'key'=>$key,
                        'file'=>UploadedFile::fromPhp([
                            'name'=>$input['name'][$key]??null,
                            'tmp_name'=>$input['tmp_name'][$key]??null,
                            'error'=>$error,
                        ]),
                    ];
                }
            }
            $created=[];
            $saved=false;
            try{
                foreach($uploads as $upload){
                    if($upload['type']==='asset_file'){
                        $result=BrandImageStore::upload(App::site(),$upload['file']);
                        $brand[$upload['key']]=$result['url'];
                        $created[]=['image',$result['token']];
                    }else{
                        $ref=BrandFontStore::upload(App::site(),$upload['file']);
                        $brand[$upload['key']]=$ref;
                        unset($brand[str_replace('_file','',$upload['key'])]);
                        $created[]=['font',$ref];
                    }
                }
                AppearanceSettings::save($pdo,App::site(),$version,$theme,$brand);
                $saved=true;
            }finally{
                if(!$saved)foreach($created as [$type,$token]){
                    if($type==='image')BrandImageStore::discard(App::site(),$token);
                    else BrandFontStore::discard(App::site(),$token);
                }
            }
            header('Location: /admin/appearance.php?saved=1',true,303);
            exit;
        }catch(\InvalidArgumentException|\DomainException $e){
            http_response_code(422);
            $message='<p role="alert">'.App::h($e->getMessage()).'</p>';
        }
    }
    $state=AppearanceSettings::read($pdo,App::site());

    // Use the real published kit defaults, rather than a guessed #777777.
    // The built-in palette is read-only and no remote CSS or font is requested.
    $variableNames=[
        'primary'=>'primary','primary_strong'=>'primary-strong',
        'secondary'=>'secondary','background'=>'bg','surface'=>'surface',
        'text'=>'text','muted'=>'muted','accent_soft'=>'accent-soft','border'=>'border',
    ];
    $fallback=[
        'primary'=>'#245e43','primary_strong'=>'#174530','secondary'=>'#245e43',
        'background'=>'#ffffff','surface'=>'#ffffff','text'=>'#101418',
        'muted'=>'#5e6770','accent_soft'=>'#f5f7f6','border'=>'#dfe3e5',
    ];
    $palettes=[];
    foreach(AppearanceSettings::THEMES as $themeId=>$themeLabel){
        $appearance=\Dendrila\Cms\Runtime\Presentation::forTheme($themeId,[]);
        $source=(string)file_get_contents($appearance['design_kit']['entrypoint_path']);
        $palette=$fallback;
        foreach($variableNames as $key=>$cssName){
            if(preg_match('/--dendrila-color-'.preg_quote($cssName,'/').'\s*:\s*(#[0-9A-Fa-f]{6})\s*;/Dmi',$source,$match)){
                $palette[$key]=strtolower($match[1]);
            }
        }
        // Some themes deliberately use primary also as their secondary hue.
        if(!preg_match('/--dendrila-color-secondary\s*:/',$source))$palette['secondary']=$palette['primary'];
        $palettes[$themeId]=$palette;
    }
    $displayColors=[
        'primary'=>['Boutons principaux','Le fond des actions prioritaires.'],
        'primary_strong'=>['Boutons au survol','Variante foncée des actions.'],
        'secondary'=>['Accents secondaires','Couleur complémentaire du thème.'],
        'background'=>['Fond général','Arrière-plan de la page.'],
        'surface'=>['Cartes et contenus','Fond des cartes et encadrés.'],
        'accent_soft'=>['Sections mises en avant','Fond des bandeaux et blocs d’accent.'],
        'text'=>['Texte principal','Titres et paragraphes.'],
        'muted'=>['Texte secondaire','Informations moins importantes.'],
        'border'=>['Contours','Traits de séparation et cadres.'],
    ];
    $groups=[
        'buttons'=>['Boutons et liens','Choisissez la teinte des actions et des éléments qui attirent l’attention.',
            ['primary','primary_strong','secondary']],
        'sections'=>['Fonds et sections','Réglez les aplats de la page, les cartes et les bandeaux.',
            ['background','surface','accent_soft']],
        'details'=>['Textes et contours','Ajustements utiles pour la lisibilité et les séparations.',
            ['text','muted','border']],
    ];

    $body='<div class="appearance-screen" data-appearance-editor>';
    $body.='<p class="appearance-back"><a href="/admin/">← Administration</a></p>';
    $body.='<div class="appearance-intro"><div><p class="appearance-eyebrow">Identité visuelle · Dendrila</p>';
    $body.='<h2>Personnaliser les couleurs</h2><p>Voyez immédiatement le rendu des boutons et des sections. Les changements restent en aperçu tant que vous ne les enregistrez pas.</p></div>';
    $body.='<a class="appearance-open-site" href="/" target="_blank" rel="noopener noreferrer">Ouvrir le site ↗</a></div>';
    if(isset($_GET['saved']))$message='<p class="appearance-notice" role="status">Apparence enregistrée. Les pages publiques et leurs aperçus utilisent les nouveaux réglages.</p>';
    if($message!=='')$body.=$message;
    if(getenv('DENDRILA_BRAND_JSON')!==false)$body.='<p class="appearance-note" role="note">Certains réglages du serveur peuvent remplacer les couleurs choisies ici.</p>';
    $body.='<form method="post" action="/admin/appearance.php" enctype="multipart/form-data" data-appearance-form>';
    $body.='<input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';
    $body.='<input type="hidden" name="version" value="'.hash('sha256',$state['raw']).'">';
    $body.='<div class="appearance-workspace"><div class="appearance-controls">';

    $body.='<fieldset class="appearance-panel appearance-theme"><legend>Thème graphique</legend>';
    $body.='<label for="appearance-theme">Direction visuelle</label><select id="appearance-theme" name="theme" required data-theme-select>';
    foreach(AppearanceSettings::THEMES as $key=>$label){
        $body.='<option value="'.App::h($key).'" data-palette="'.App::h(json_encode($palettes[$key],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).'"'
            .($state['theme']===$key?' selected':'').'>'.App::h($label).'</option>';
    }
    $body.='</select><p class="appearance-helper">Le changement de thème conserve les pages, menus et blocs.</p></fieldset>';

    foreach($groups as $groupKey=>[$title,$description,$keys]){
        $body.='<fieldset class="appearance-panel appearance-group" data-color-group="'.App::h($groupKey).'">';
        $body.='<legend>'.App::h($title).'</legend><p class="appearance-helper">'.App::h($description).'</p>';
        $body.='<div class="appearance-swatch-grid">';
        foreach($keys as $key){
            [$label,$description]= $displayColors[$key];
            $custom=array_key_exists($key,$state['brand']);
            $color=$custom?$state['brand'][$key]:$palettes[$state['theme']][$key];
            $id='appearance-color-'.$key;
            $body.='<div class="appearance-swatch" data-color-key="'.App::h($key).'">';
            $body.='<div class="appearance-swatch-top"><label class="appearance-swatch-name" for="'.$id.'">'.App::h($label).'</label>';
            $body.='<span class="appearance-swatch-status" data-color-status>'.($custom?'Personnalisée':'Thème').'</span></div>';
            $body.='<p>'.App::h($description).'</p>';
            $body.='<div class="appearance-swatch-bottom">';
            $body.='<input class="appearance-color-picker" id="'.$id.'" type="color" name="color['.App::h($key).']" value="'.App::h($color).'" aria-label="'.App::h($label).' : choisir la couleur" data-picker>';
            $body.='<output class="appearance-hex" for="'.$id.'" data-hex>'.App::h(strtoupper($color)).'</output>';
            $body.='<label class="appearance-override"><input type="checkbox" name="enabled[]" value="'.App::h($key).'"'.($custom?' checked':'').' data-override> Appliquer</label>';
            $body.='</div></div>';
        }
        $body.='</div></fieldset>';
    }

    $body.='<fieldset class="appearance-panel appearance-fonts"><legend>Polices d’écriture</legend>';
    $body.='<p class="appearance-helper">Testez une police sur les titres ou les paragraphes, ou importez la vôtre. Les polices sont hébergées sur ce site, sans service extérieur.</p>';
    $fontStyles=[
        'neutral-sans'=>'Sans empattements — classique',
        'humanist-sans'=>'Sans empattements — souple',
        'editorial-serif'=>'Avec empattements — classique',
        'system-serif'=>'Avec empattements — système',
    ];
    foreach(['heading_font'=>['Titres','Le titre donne immédiatement le ton.','heading_font_file'],
             'body_font'=>['Textes et paragraphes','Le texte doit rester facile à lire sur mobile.','body_font_file']] as $key=>[$label,$sample,$fileKey]){
        $current=$state['brand'][$fileKey]??'';
        $selection=$current!==''?'custom':($state['brand'][$key]??'');
        $body.='<div class="appearance-font-card" data-font-target="'.App::h($key).'" data-current-font="'.App::h($current).'">';
        $body.='<label for="appearance-font-'.$key.'">'.App::h($label).'</label>';
        $body.='<select name="'.App::h($key).'" id="appearance-font-'.$key.'" data-font-select>';
        $body.='<option value=""'.($selection===''?' selected':'').'>Police du thème</option>';
        foreach($fontStyles as $value=>$name)
            $body.='<option value="'.App::h($value).'"'.($selection===$value?' selected':'').'>'.App::h($name).'</option>';
        $body.='<option value="custom"'.($selection==='custom'?' selected':'').($current===''?' disabled':'').'>Ma police importée</option>';
        $body.='</select>';
        $body.='<div class="appearance-font-sample" data-font-sample>'.App::h($sample).'</div>';
        $body.='<label class="appearance-font-upload-label" for="appearance-file-'.$key.'">Importer ma police (WOFF2, WOFF, TTF, OTF · 3 Mo max.)</label>';
        $body.='<input type="file" name="font_file['.$fileKey.']" id="appearance-file-'.$key.'" accept=".woff2,.woff,.ttf,.otf" data-font-upload>';
        $body.='<p class="appearance-font-status" role="status" data-font-status>'.($current!==''?'Police personnalisée enregistrée.':'Aucune police importée.').'</p>';
        if($current!=='')$body.='<label class="appearance-remove"><input type="checkbox" name="remove_font[]" value="'.$fileKey.'" data-font-remove> Supprimer cette personnalisation</label>';
        $body.='</div>';
    }
    $body.='</fieldset>';
    $body.='<details class="appearance-panel appearance-details"><summary>Images du site <span>Logo et bandeau</span></summary>';
    $body.='<div class="appearance-details-content"><h3>Images de marque</h3>';
    $body.='<p class="appearance-helper">Sélectionnez une image depuis votre appareil. JPEG, PNG ou WebP, 8 Mo maximum. Les logos déjà configurés restent conservés.</p>';
    $assets=[
        'logo_url'=>['Logo (symbole)','Une icône ou un symbole qui représente votre marque.'],
        'wordmark_url'=>['Logo avec nom (facultatif)','Version avec le nom de votre marque. Inutile si votre logo contient déjà le nom.'],
        'hero_image_url'=>['Image du bandeau','Illustration ou photo présentée en haut de la page d’accueil.'],
    ];
    foreach($assets as $key=>[$label,$explanation]){
        $url=$state['brand'][$key]??'';
        $body.='<section class="appearance-asset" data-brand-asset="'.$key.'"><h4>'.App::h($label).'</h4><p>'.App::h($explanation).'</p>';
        $body.='<div class="appearance-asset-preview'.($key==='hero_image_url'?' appearance-asset-preview--banner':'').'">';
        $body.='<img data-brand-preview src="'.App::h($url).'" alt="Aperçu : '.App::h($label).'"'.($url===''?' hidden':'').'>';
        $body.='<span data-brand-placeholder'.($url!==''?' hidden':'').'>Aucune image sélectionnée</span></div>';
        $body.='<label for="appearance-asset-'.$key.'">Choisir une image</label>';
        $body.='<input type="file" class="appearance-asset-file" name="asset_file['.$key.']" id="appearance-asset-'.$key.'" accept="image/jpeg,image/png,image/webp" data-brand-file>';
        if($url!=='')$body.='<label class="appearance-remove"><input type="checkbox" name="remove_asset[]" value="'.$key.'" data-brand-remove> Retirer cette image</label>';
        $body.='</section>';
    }
    $body.='</div></details>';
    $body.='</div>';

    // Lightweight structural preview: no iframe, external scripts, network or
    // generated page data. All adjustable values are fed through theme palettes.
    $body.='<aside class="appearance-preview-column" aria-label="Aperçu des couleurs">';
    $body.='<div class="appearance-preview-heading"><div><h3>Aperçu en direct</h3><p>Exemple de page — rien n’est publié.</p></div>';
    $body.='<span class="appearance-draft-pill" data-change-state>Réglages actuels</span></div>';
    $body.='<div class="appearance-preview" data-color-preview>';
    $body.='<div class="appearance-mini-nav"><strong>Votre site</strong><span>Accueil</span><span>Services</span><span class="appearance-mini-link">Contact</span></div>';
    $body.='<div class="appearance-mini-hero"><p class="appearance-mini-kicker">Présentation</p>';
    $body.='<h4>Une activité, des prestations à découvrir.</h4>';
    $body.='<p class="appearance-mini-muted">Un aperçu des titres, paragraphes et couleurs utilisés sur votre site.</p>';
    $body.='<div class="appearance-mini-actions"><span class="appearance-mini-button">Demander un devis</span><span class="appearance-mini-secondary">En savoir plus</span></div></div>';
    $body.='<div class="appearance-mini-section"><p class="appearance-mini-kicker">Nos prestations</p><h4>Ce que nous proposons</h4>';
    $body.='<div class="appearance-mini-cards"><div class="appearance-mini-card"><strong>Prestation principale</strong><p>Description du service.</p><span>Découvrir →</span></div>';
    $body.='<div class="appearance-mini-card"><strong>Accompagnement</strong><p>Modalités et conditions.</p><span>Découvrir →</span></div></div></div>';
    $body.='<div class="appearance-mini-callout"><strong>Une information importante</strong><p>Exemple de section avec un fond coloré.</p></div>';
    $body.='</div><div class="appearance-contrast"><p class="appearance-contrast-status" data-contrast-status role="status">Vérification des contrastes…</p>';
    $body.='<p class="appearance-helper">L’aperçu montre les couleurs, pas la mise en page exacte du thème. Le contraste des textes et fonds est contrôlé avant enregistrement.</p></div>';
    $body.='</aside></div>';
    $body.='<div class="appearance-bottom"><p data-save-hint>Vous pouvez modifier les couleurs et vérifier le résultat avant de publier.</p>';
    $body.='<button class="button appearance-save" type="submit" data-save-button>Enregistrer les changements</button></div>';
    $body.='</form></div>';
    $page=App::page('Apparence du site',$body);
    echo str_replace('<body>',
        '<link rel="stylesheet" href="/backoffice-assets/appearance.css?v=3">'
        .'<script defer src="/backoffice-assets/appearance.js?v=3"></script><body>', $page);
}catch(\Throwable $e){
    error_log('Dendrila appearance: '.$e::class.': '.$e->getMessage());
    http_response_code(503);echo App::page('Apparence indisponible','<p>Consultez les journaux du serveur.</p>');
}
