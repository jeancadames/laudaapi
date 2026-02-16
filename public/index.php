<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/**
 * ✅ OpenSSL Legacy Provider (OpenSSL 3.x)
 * OpenSSL y algunas librerías leen configuración desde el ENV real (getenv()).
 * Dotenv llena $_ENV/$_SERVER, pero no siempre exporta al environment del proceso.
 *
 * Requisito:
 *   .env => OPENSSL_CONF=/etc/ssl/openssl-legacy.cnf
 *
 * Esto debe ocurrir ANTES del autoloader para cubrir todo (incluyendo libs que cargan temprano).
 */

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
