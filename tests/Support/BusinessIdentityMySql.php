<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

/** Opt-in schemas on the existing disposable server. Never loads operational configuration. */
final class BusinessIdentityMySql
{
    public const PROJECTS = ['central', 'ecf', 'pos', 'bys', 'cumplimiento'];

    public static function server(string $server): array
    {
        require_once dirname(__DIR__, 3).'/byslaudaapi/tests/Support/BysRaceDatabase.php';

        return BysRaceDatabase::server($server);
    }

    public static function privateDirectory(string $path): void
    {
        if (! is_dir($path) || is_link($path) || realpath($path) !== $path || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Se requiere un directorio privado, canónico y sin enlaces.');
        }
    }

    public static function read(string $file): array
    {
        if (! is_file($file) || is_link($file) || (fileperms($file) & 0077) !== 0) {
            throw new RuntimeException('Falta un manifiesto privado de esta ejecución.');
        }

        return json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function marker(string $base): array
    {
        if (! preg_match('#^/tmp/lauda-business-[a-f0-9]{16}$#D', $base)) {
            throw new RuntimeException('Runtime Business inválido.');
        }
        self::privateDirectory($base);
        $marker = self::read($base.'/mysql-run.json');
        if (! in_array($marker['purpose'] ?? '', ['business-f2-mysql', 'business-f5-mysql'], true) || ($marker['base'] ?? '') !== $base) {
            throw new RuntimeException('El marcador no pertenece a esta ejecución.');
        }

        return $marker;
    }

    private static function database(string $runtime): string
    {
        return 'business_test_'.substr(hash('sha256', $runtime), 0, 16);
    }

    public static function configuration(string $runtime): array
    {
        $marker = self::marker(dirname($runtime));
        self::privateDirectory($runtime);
        $manifest = self::read($runtime.'/mysql.json');
        if (! in_array(basename($runtime), self::PROJECTS, true)
            || ($manifest['database'] ?? '') !== self::database($runtime)
            || ($manifest['server'] ?? '') !== $marker['server']
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['password'] ?? '')) {
            throw new RuntimeException('Configuración MySQL fuera del alcance de esta ejecución.');
        }
        self::server($marker['server']);

        return ['driver' => 'mysql', 'host' => 'localhost', 'unix_socket' => $marker['server'].'/mysql.sock',
            'database' => $manifest['database'], 'username' => $manifest['database'], 'password' => $manifest['password'],
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
            'options' => [PDO::ATTR_TIMEOUT => 5]];
    }

    public static function prepare(string $base): array
    {
        $marker = self::marker($base);
        $instance = self::server($marker['server']);
        $pdo = $instance['pdo'];
        $names = [];
        foreach (self::PROJECTS as $project) {
            $runtime = $base.'/'.$project;
            self::privateDirectory($runtime);
            if (file_exists($runtime.'/mysql.json')) {
                throw new RuntimeException('La preparación requiere una ejecución nueva.');
            }
            $database = self::database($runtime);
            $exists = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $exists->execute([$database]);
            $user = $pdo->prepare('SELECT User FROM mysql.user WHERE User = ?');
            $user->execute([$database]);
            if ($exists->fetchColumn() !== false || $user->fetchColumn() !== false) {
                throw new RuntimeException('El nombre descartable ya existe; no se reutiliza.');
            }
            $password = bin2hex(random_bytes(32));
            $file = fopen($runtime.'/mysql.json', 'x');
            if ($file === false) {
                throw new RuntimeException('No se pudo registrar el esquema antes de crearlo.');
            }
            chmod($runtime.'/mysql.json', 0600);
            fwrite($file, json_encode(['server' => $marker['server'], 'database' => $database, 'password' => $password], JSON_THROW_ON_ERROR));
            fclose($file);
            $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec("CREATE USER '".$database."'@'localhost' IDENTIFIED BY ".$pdo->quote($password));
            // With partial_revokes off, database-level grants interpret underscores as wildcards.
            $grantDatabase = (int) $pdo->query('SELECT @@partial_revokes')->fetchColumn() === 1
                ? $database : str_replace('_', '\\_', $database);
            $pdo->exec('GRANT ALL PRIVILEGES ON `'.$grantDatabase."`.* TO '".$database."'@'localhost'");
            $names[$project] = $database;
        }

        return ['version' => $instance['version'], 'datadir' => $instance['datadir'], 'schemas' => $names];
    }

    public static function cleanup(string $base): array
    {
        $marker = self::marker($base);
        $pdo = self::server($marker['server'])['pdo'];
        $removed = [];
        foreach (self::PROJECTS as $project) {
            $runtime = $base.'/'.$project;
            if (! is_file($runtime.'/mysql.json')) {
                continue;
            }
            $database = self::configuration($runtime)['database'];
            $active = $pdo->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = ?');
            $active->execute([$database]);
            if ((int) $active->fetchColumn() !== 0) {
                throw new RuntimeException('Quedan conexiones activas en el esquema descartable: '.$database);
            }
            $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
            $pdo->exec("DROP USER IF EXISTS '".$database."'@'localhost'");
            $remaining = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $remaining->execute([$database]);
            $users = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ?');
            $users->execute([$database]);
            if ((int) $remaining->fetchColumn() !== 0 || (int) $users->fetchColumn() !== 0) {
                throw new RuntimeException('La limpieza no se pudo verificar.');
            }
            $removed[] = $database;
        }

        return $removed;
    }
}
