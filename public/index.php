<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// ----------------------------------------------------------
// Check if app is installed via .env variable
// ----------------------------------------------------------
$envPath = __DIR__ . '/../.env';
$appInstalled = false;

if (file_exists($envPath)) {
    $envContent = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envContent as $line) {
        if (strpos(trim($line), 'APP_INSTALLED=') === 0) {
            $value = trim(explode('=', $line, 2)[1] ?? '');
            $appInstalled = strtolower($value) === 'true';
            break;
        }
    }
}

if (!$appInstalled) {
    // dd('Application is not installed. Redirecting to installation wizard...');
    header('Location: install-app.php');
    exit;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
