<?php

require_once dirname(__DIR__, 2).'/Support/BusinessIdentityMySql.php';

use Tests\Support\BusinessIdentityMySql;

umask(0077);
try {
    $result = match ($argv[1] ?? '') {
        'preflight' => array_diff_key(BusinessIdentityMySql::server($argv[2] ?? ''), ['pdo' => true]),
        'manifest' => BusinessIdentityMySql::marker($argv[2] ?? ''),
        'prepare' => BusinessIdentityMySql::prepare($argv[2] ?? ''),
        'cleanup' => BusinessIdentityMySql::cleanup($argv[2] ?? ''),
        default => throw new RuntimeException('Acción desconocida.'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(2);
}
