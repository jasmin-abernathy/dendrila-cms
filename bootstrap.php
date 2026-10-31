<?php
declare(strict_types=1);
require_once __DIR__.'/vendor/autoload.php';
$configPath = getenv('DENDRILA_CONFIG') ?: __DIR__.'/config/local.php';
if (is_file($configPath)) {
    $settings = require $configPath;
    if (!is_array($settings)) throw new RuntimeException('Invalid private configuration.');
    foreach ($settings as $key => $value) {
        if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]+$/D', $key) && is_scalar($value) && getenv($key) === false) {
            putenv($key.'='.(string)$value);
        }
    }
}
