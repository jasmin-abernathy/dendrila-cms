<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\SessionCsrf;
App::session();header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$csrf=new SessionCsrf();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:'')){http_response_code(403);$error='Formulaire expiré.';}
    else try{
        $email=is_string($_POST['email']??null)?$_POST['email']:'';$password=is_string($_POST['password']??null)?$_POST['password']:'';$code=is_string($_POST['code']??null)?$_POST['code']:'';
        if(App::identity(App::database())->login($email,$password,$code,$_SERVER['REMOTE_ADDR']??'')){header('Location: /admin/',true,303);exit;}
        http_response_code(401);$error='Connexion refusée. Vérifiez vos identifiants et le code ; après plusieurs essais, patientez 15 minutes.';
    }catch(Throwable $e){error_log('Dendrila login: '.$e::class);http_response_code(503);$error='Connexion temporairement indisponible.';}
}
echo App::page('Connexion',($error!==''?'<p role="alert" class="notice">'.App::h($error).'</p>':'').'<form method="post"><input type="hidden" name="csrf" value="'.App::h($csrf->token()).'"><label class="field">E-mail<input required type="email" name="email" autocomplete="username"></label><label class="field">Mot de passe<input required type="password" name="password" autocomplete="current-password"></label><label class="field">Code de votre application d’authentification<input required name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code"></label><button class="button">Se connecter</button></form>');

