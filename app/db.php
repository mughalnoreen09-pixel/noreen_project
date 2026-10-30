<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $host = (string)($config['db_host'] ?? '127.0.0.1');
    $dbname = (string)($config['db_name'] ?? 'salondiva');
    $user = (string)($config['db_user'] ?? 'root');
    $pass = (string)($config['db_pass'] ?? '');
    $port = !empty($config['db_port']) ? (int)$config['db_port'] : 3306;
    $portStr = ($port > 0 && $port !== 3306) ? ";port={$port}" : '';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $hostsToTry = array_unique([$host, '127.0.0.1', 'localhost']);
    $lastError = null;

    foreach ($hostsToTry as $currentHost) {
        try {
            $dsn = sprintf('mysql:host=%s%s;dbname=%s;charset=utf8mb4', $currentHost, $portStr, $dbname);
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;

            // If error is Unknown Database (code 1049), auto-create database and initialize tables
            if ($e->getCode() === 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $serverDsn = sprintf('mysql:host=%s%s;charset=utf8mb4', $currentHost, $portStr);
                    $serverPdo = new PDO($serverDsn, $user, $pass, $options);
                    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO($dsn, $user, $pass, $options);

                    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'salondiva.sql';
                    if (file_exists($sqlFile)) {
                        $sql = file_get_contents($sqlFile);
                        if ($sql !== false && trim($sql) !== '') {
                            $pdo->exec($sql);
                        }
                    }
                    return $pdo;
                } catch (Throwable $initEx) {
                    $lastError = $initEx;
                }
            }
        }
    }

    throw new RuntimeException(
        'Database connection failed. Please ensure MySQL is running in XAMPP: ' . ($lastError ? $lastError->getMessage() : 'unknown error'),
        0,
        $lastError
    );
}
