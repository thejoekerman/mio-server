<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

if ('test' === ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null)) {
    ensureMysqlTestDatabaseExists($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? null);
}

function ensureMysqlTestDatabaseExists(?string $databaseUrl): void
{
    if (null === $databaseUrl || '' === $databaseUrl) {
        return;
    }

    $parts = parse_url($databaseUrl);

    if (false === $parts || 'mysql' !== ($parts['scheme'] ?? null)) {
        return;
    }

    $databaseName = ltrim($parts['path'] ?? '', '/');

    if ('' === $databaseName) {
        return;
    }

    $host = $parts['host'] ?? '127.0.0.1';
    $port = $parts['port'] ?? 3306;
    $user = $parts['user'] ?? null;
    $password = $parts['pass'] ?? null;

    parse_str($parts['query'] ?? '', $query);
    $charset = is_string($query['charset'] ?? null) ? $query['charset'] : 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
        str_replace('`', '``', $databaseName),
        $charset,
        $charset,
    ));
}
