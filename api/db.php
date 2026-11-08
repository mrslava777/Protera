<?php
declare(strict_types=1);

/** Подключение к БД: MySQL на хостинге, SQLite для локальной проверки. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = (string)cfg('db.driver');
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        if ($driver === 'sqlite') {
            $path = (string)cfg('db.path');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string)cfg('db.host'),
                (int)cfg('db.port'),
                (string)cfg('db.name')
            );
            $pdo = new PDO($dsn, (string)cfg('db.user'), (string)cfg('db.pass'), $options);
        }
    } catch (PDOException $e) {
        error_log('[protera] DB connection failed: ' . $e->getMessage());
        json_out(['ok' => false, 'code' => 'db', 'message' => 'База данных недоступна. Проверьте настройки в api/config.php'], 500);
    }

    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_val(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_id(): int
{
    return (int)db()->lastInsertId();
}
