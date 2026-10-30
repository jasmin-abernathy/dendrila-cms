<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/bootstrap.php';
$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_id VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = $pdo->query('SELECT migration_id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
    $migrationId = basename($file);
    if (in_array($migrationId, $applied, true)) {
        continue;
    }
    $delimiter = ';';
    $buffer = '';
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), $delimiter)) {
            $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            $buffer = '';
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException("Unterminated SQL statement in {$migrationId}.");
    }
    $record = $pdo->prepare('INSERT INTO schema_migrations (migration_id, applied_at) VALUES (:id, UTC_TIMESTAMP(6))');
    $record->execute(['id' => $migrationId]);
    fwrite(STDOUT, "Applied {$migrationId}\n");
}
