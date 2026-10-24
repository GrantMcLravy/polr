<?php
// Print, as JSON, what env() returns for every key in DIR/.env when loaded the
// way the running app loads it. Usage: php envprobe.php APP_ROOT DIR
//
// For the original app this is phpdotenv 1.1 (Dotenv::load) + Lumen's env().
require $argv[1] . '/vendor/autoload.php';

$dir = $argv[2];
$keys = [];
foreach (file($dir . '/.env') as $line) {
    if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=/', $line, $m)) {
        $keys[] = $m[1];
    }
}

try {
    if (class_exists('Dotenv\Dotenv') && method_exists('Dotenv\Dotenv', 'createImmutable')) {
        Dotenv\Dotenv::createImmutable($dir)->load();
    } else {
        Dotenv::load($dir);
    }
    $out = [];
    foreach (array_unique($keys) as $k) {
        $out[$k] = env($k);
    }
} catch (Throwable $e) {
    $out = ['__error__' => get_class($e) . ': ' . $e->getMessage()];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
