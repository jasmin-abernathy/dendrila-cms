<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use Dendrila\Cms\Runtime\Application;
use Dendrila\Cms\Installation\{Migrator,SqliteMigrator};
use Dendrila\Cms\Storage\SqliteConnection;

$dsn=getenv('DB_DSN')?:'';
if($dsn===''){
    fwrite(STDERR,"Aucune base locale configurée : terminez l'installation avant la migration.\n");
    exit(2);
}
$pdo=Application::database();
$applied=$pdo instanceof SqliteConnection
    ? (new SqliteMigrator($pdo))->run()
    : (new Migrator($pdo))->run();
foreach($applied as $id)fwrite(STDOUT,"Applied ".$id."\n");
if($applied===[])fwrite(STDOUT,"All migrations are already applied.\n");
