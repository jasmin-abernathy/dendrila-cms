<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Forum\{ForumService,ForumPhotoStorage};
use Dendrila\Cms\Authorization\{AuthorizationService,AccessDenied};
use Dendrila\Cms\Storage\PdoCmsStore;
if(!in_array('forum',App::addons(),true)){http_response_code(404);exit('Module non activé.');}
App::session();$pdo=App::database();$actor=App::identity($pdo)->currentActor();$store=new PdoCmsStore($pdo);
$forum=new ForumService($pdo,new AuthorizationService($store),new ForumPhotoStorage(getenv('FORUM_PRIVATE_ROOT')?:''));$csrf=new SessionCsrf();
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$moderator=$actor&&$store->actorHasPermission($actor->siteId,$actor->actorId,'forum.moderate');$canPost=$actor&&$store->actorHasPermission($actor->siteId,$actor->actorId,'forum.post');$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
    if(!$actor||!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new AccessDenied();
    $text=static fn(string $key):string=>is_string($_POST[$key]??null)?$_POST[$key]:'';
    switch($text('action')){
        case 'topic':$forum->openTopic($actor,$text('title'),$text('body'));break;
        case 'reply':$forum->reply($actor,$text('topic'),$text('body'));break;
        case 'moderate_topic':$forum->moderateTopic($actor,$text('id'),$text('decision')==='publish');break;
        case 'moderate_message':$forum->moderateMessage($actor,$text('id'),$text('decision')==='publish');break;
        case 'lock':$forum->lockTopic($actor,$text('id'),$text('locked')==='1');break;
        default:throw new InvalidArgumentException('Action inconnue.');
    }
    header('Location: /forum/?saved=1',true,303);exit;
}catch(AccessDenied){http_response_code(403);$error='Action interdite ou formulaire expiré.';}catch(InvalidArgumentException|DomainException $e){http_response_code(422);$error=$e->getMessage();}
$hidden='<input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';$body='<a href="/admin/">Mon espace</a>';
if($error!=='')$body.='<p role="alert">'.App::h($error).'</p>';
if(($_GET['saved']??'')==='1')$body.='<p role="status">Action enregistrée. Les nouvelles contributions attendent une validation avant publication.</p>';
if($moderator){$queue=$forum->moderationQueue($actor);$body.='<section class="panel"><h2>À modérer</h2>';
    foreach(['topics'=>'moderate_topic','messages'=>'moderate_message'] as $kind=>$action)foreach($queue[$kind] as $row){$id=$row[$kind==='topics'?'topic_id':'message_id'];$body.='<article><h3>'.App::h($row['title']??'Réponse').'</h3>';
        if($kind==='topics'){$q=$pdo->prepare('SELECT body FROM forum_messages WHERE site_id=? AND topic_id=? AND is_opener=1');$q->execute([App::site(),$id]);$body.='<p>'.nl2br(App::h($q->fetchColumn())).'</p>';}else $body.='<p>'.nl2br(App::h($row['body'])).'</p>';
        $body.='<form method="post">'.$hidden.'<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.App::h($id).'"><button name="decision" value="publish">Publier</button> <button name="decision" value="hide">Refuser</button></form></article>';
    }$body.='</section>';
}
$id=is_string($_GET['topic']??null)?$_GET['topic']:'';
if($id!==''&&preg_match('/^[a-f0-9]{32}$/D',$id)){
    $topic=$forum->visibleTopic(App::site(),$id);
    if(!$topic){http_response_code(404);$body.='<p>Sujet introuvable.</p>';}else{
        $body.='<h2>'.App::h($topic['title']).'</h2>';
        foreach($forum->visibleMessages(App::site(),$id) as $message)$body.='<article class="panel"><p>'.nl2br(App::h($message['body'])).'</p></article>';
        if($canPost&&!$topic['locked'])$body.='<form method="post">'.$hidden.'<input type="hidden" name="action" value="reply"><input type="hidden" name="topic" value="'.App::h($id).'"><label class="field">Votre réponse<textarea required name="body" maxlength="20000"></textarea></label><button class="button">Envoyer pour validation</button></form>';
        if($moderator)$body.='<form method="post">'.$hidden.'<input type="hidden" name="action" value="lock"><input type="hidden" name="id" value="'.App::h($id).'"><button name="locked" value="'.($topic['locked']?'0':'1').'">'.($topic['locked']?'Rouvrir':'Fermer').' les réponses</button></form>';
    }
}else{
    $body.='<h2>Discussions</h2><ul>';foreach($forum->visibleTopics(App::site()) as $topic)$body.='<li><a href="?topic='.App::h($topic['topic_id']).'">'.App::h($topic['title']).'</a></li>';$body.='</ul>';
    if($canPost)$body.='<h2>Ouvrir une discussion</h2><form method="post">'.$hidden.'<input type="hidden" name="action" value="topic"><label class="field">Titre<input required name="title" maxlength="160"></label><label class="field">Message<textarea required name="body" maxlength="20000"></textarea></label><button class="button">Envoyer pour validation</button></form>';
    elseif(!$actor)$body.='<p><a href="/admin/login.php">Connectez-vous</a> pour participer. Les comptes sont créés sur invitation.</p>';
}
echo App::page('Forum',$body);

