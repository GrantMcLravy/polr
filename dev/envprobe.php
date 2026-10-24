<?php
// Print, as JSON, what env() returns for every key in DIR/.env when loaded by
// the port (App\Bootstrap\LoadEnvironmentVariables). Compare the output with
// baseline/fixtures/env/*.parsed.json (phpdotenv 1.1 in Polr 2.3.0).
//
// Usage: php dev/envprobe.php DIR   (run inside the dev container)
require __DIR__.'/../vendor/autoload.php';

$dir = $argv[1];
$keys = [];
foreach (file($dir.'/.env') as $line) {
    if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=/', $line, $m)) {
        $keys[] = $m[1];
    }
}

try {
    App\Bootstrap\LoadEnvironmentVariables::load($dir.'/.env', Illuminate\Support\Env::getRepository());
    $out = [];
    foreach (array_unique($keys) as $k) {
        $out[$k] = env($k);
    }
} catch (Throwable $e) {
    $out = ['__error__' => get_class($e).': '.$e->getMessage()];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
