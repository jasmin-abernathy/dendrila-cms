<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$pdo = new PDO(getenv('DB_DSN') ?: '', getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$media = Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo);
if ($media === null) throw new RuntimeException('Configure media storage first.');
fwrite(STDOUT, $media->collectGarbage() . " médias supprimés ; temporaires orphelins nettoyés.\n");
