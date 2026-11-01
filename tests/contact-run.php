<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Front\PublicContactForm;
function contactCheck(bool $valid,string $description):void {
    if (!$valid) throw new RuntimeException($description);
}
putenv('DENDRILA_CONTACT_EMAIL=');
contactCheck(PublicContactForm::recipient()===null,'Unconfigured contact never sends.');
putenv('DENDRILA_CONTACT_EMAIL=contact@example.test');
putenv('APP_BASE_URL=');
contactCheck(PublicContactForm::context()['enabled']===false,
    'Missing outbound sender configuration keeps installed form disabled.');
putenv('APP_BASE_URL=https://example.test');
putenv('DENDRILA_CONTACT_THROTTLE_DIR='.sys_get_temp_dir().'/dendrila-contact-tests-'.bin2hex(random_bytes(7)));
putenv('DENDRILA_AUTH_KEY=contact-tests-secret');
putenv('DENDRILA_SITE_ID=test-site');
$context=PublicContactForm::context();
contactCheck($context['enabled']===true && strlen($context['token'])===64,'Valid recipient yields a form token.');
$payload=[
    'csrf'=>$context['token'],'name'=>'Camille Demo','email'=>'camille@example.test',
    'subject'=>'Contact et projet','message'=>'Bonjour, je souhaiterais vous contacter.',
    'website'=>'',
];
$sent=[];
$send=static function(string $to,string $subject,string $body,array $headers)use(&$sent):bool {
    $sent=compact('to','subject','body','headers');
    return true;
};
contactCheck(str_contains(PublicContactForm::submit($payload,'192.0.2.11',$send),'patienter'),
    'Minimum submission delay prevents immediate bot sends.');
$_SESSION['dendrila_contact']['created']=time()-4;
$invalid=$payload;$invalid['csrf']=str_repeat('1',64);
contactCheck(str_contains(PublicContactForm::submit($invalid,'192.0.2.11',$send),'session'),
    'Invalid CSRF refused.');
contactCheck(str_contains(PublicContactForm::submit($payload,'192.0.2.11',$send),'bien été transmis'),
    'Valid submission accepted by injected local transport.');
contactCheck(($sent['to']??null)==='contact@example.test'
    && str_contains($sent['body']??'','Objet : Contact et projet')
    && count(array_filter($sent['headers'],static fn(string $h):bool=>str_contains($h,'Reply-To: camille@example.test')))===1,
    'Email body and reply-to are correct.');
$context=PublicContactForm::context();
$_SESSION['dendrila_contact']['created']=time()-4;
$trap=$payload;$trap['csrf']=$context['token'];$trap['website']='unwanted.example';
$sent=[];
contactCheck(str_contains(PublicContactForm::submit($trap,'192.0.2.12',$send),'prise en compte')
    && $sent===[], 'Spam honeypot silently drops without sending.');
$context=PublicContactForm::context();
$bad=$payload;$bad['csrf']=$context['token'];$bad['subject']="Injected\r\nBCC:evil@example.test";
$_SESSION['dendrila_contact']['created']=time()-4;
contactCheck(str_contains(PublicContactForm::submit($bad,'192.0.2.12',$send),'objet'),
    'Header-injection attempt rejected.');
PublicContactForm::flash('Confirmation de test');
$context=PublicContactForm::context();
contactCheck($context['notice']==='Confirmation de test','PRG flash notice delivered.');
contactCheck(PublicContactForm::context()['notice']==='','PRG flash notice consumed once.');
echo "Native contact: disabled configuration, preview safety, CSRF, delay, honeypot, mail, injection, PRG OK\n";
