<?php
declare(strict_types=1);
// Test-only router. Never distributed in the hosting ZIP.
// The application still checks loopback and APP_ENV=local before allowing
// insecure transport; a synthetic hostname exercises production DNS validation.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'local'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Test-only browser router.');
}
$_SERVER['HTTP_HOST'] = 'atelier.example.test';
return require dirname(__DIR__) . '/public/router.php';
