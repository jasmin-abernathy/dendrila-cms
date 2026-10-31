<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
\Dendrila\Cms\Runtime\Application::session();
$csrf=new \Dendrila\Cms\BackOffice\SessionCsrf();
if($_SERVER['REQUEST_METHOD']!=='POST'||!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:'')){http_response_code(403);exit('Action interdite.');}
$_SESSION=[];session_destroy();header('Location: /admin/login.php',true,303);
