<?php

use Illuminate\Http\Request;

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$base = getenv('LAUDA_BUSINESS_RUNTIME').'/'.getenv('LAUDA_BUSINESS_PROJECT');
if (str_starts_with($path, '/build/') && ! str_contains($path, '..')) {
    $file = $base.'/public'.$path;
    if (is_file($file)) {
        header('Content-Type: '.match (pathinfo($file, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        });
        readfile($file);

        return;
    }
    http_response_code(404);

    return;
}
if (in_array($path, ['/favicon.svg', '/favicon-32x32.png', '/favicon-16x16.png', '/apple-touch-icon.png', '/site.webmanifest'], true)) {
    http_response_code(204);

    return;
}
$_SERVER['HTTPS'] = 'on';
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
