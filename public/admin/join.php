<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\Auth\{Accounts,Totp};
use Dendrila\Cms\BackOffice\SessionCsrf;
App::session();header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if($_SERVER['REQUEST_METHOD']==='GET'&&is_string($_GET['token']??null)&&preg_match('/^[a-f0-9]{64}$/D',$_GET['token'])){
    $_SESSION['join']=['token'=>$_GET['token'],'secret'=>Totp::secret(),'expires'=>time()+1800];header('Location: /admin/join.php',true,303);exit;
}
$join=$_SESSION['join']??null;if(!is_array($join)||$join['expires']<time()){http_response_code(410);echo App::page('Invitation indisponible','<p>Ouvrez le lien reçu de l’administrateur.</p>');exit;}
$csrf=new SessionCsrf();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
    if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
    (new Accounts(App::database(),App::site(),getenv('DENDRILA_AUTH_KEY')?:''))->redeem($join['token'],is_string($_POST['password']??null)?$_POST['password']:'',$join['secret'],is_string($_POST['code']??null)?$_POST['code']:'');
    unset($_SESSION['join']);echo App::page('Compte créé','<p>Attendez le prochain code de votre application, puis <a href="/admin/login.php">connectez-vous</a>.</p>');exit;
}catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}catch(PDOException){http_response_code(422);$error='Ce compte existe déjà ou l’invitation est indisponible.';}
echo App::page('Activer votre compte',($error!==''?'<p role="alert">'.App::h($error).'</p>':'').'<p>Ajoutez cette clé à votre application d’authentification : 6 chiffres, 30 secondes. Conservez-la dans votre gestionnaire de mots de passe.</p><code class="secret">'.App::h($join['secret']).'</code><form method="post"><input type="hidden" name="csrf" value="'.App::h($csrf->token()).'"><label class="field">Mot de passe (12 caractères minimum)<input required minlength="12" type="password" name="password" autocomplete="new-password"></label><label class="field">Code actuel<input required name="code" inputmode="numeric" pattern="[0-9]{6}"></label><button class="button">Créer mon compte</button></form>');

