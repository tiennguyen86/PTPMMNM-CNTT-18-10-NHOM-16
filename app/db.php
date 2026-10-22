<?php
defined('APP') or exit;

/** Kết nối CSDL (SQLite hoặc MySQL) – tự cài đặt bảng ở lần chạy đầu. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = config('db');
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if ($cfg['driver'] === 'mysql') {
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']);
        try {
            $pdo = new PDO($dsn . ';dbname=' . $cfg['database'], $cfg['username'], $cfg['password'], $options);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1049) throw $e;
            // CSDL chưa tồn tại → tạo mới
            $tmp = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
            $tmp->exec('CREATE DATABASE `' . str_replace('`', '', $cfg['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO($dsn . ';dbname=' . $cfg['database'], $cfg['username'], $cfg['password'], $options);
        }
    } elseif ($cfg['driver'] === 'sqlite') {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('PHP chưa bật pdo_sqlite. Hãy bật extension này trong php.ini hoặc chuyển driver sang "mysql" trong config.php.');
        }
        $dir = dirname($cfg['sqlite_path']);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $cfg['sqlite_path'], null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        throw new RuntimeException('Driver CSDL không hợp lệ: ' . $cfg['driver']);
    }

    install_if_needed($pdo, $cfg['driver']);
    return $pdo;
}

function db_driver(): string
{
    return config('db.driver');
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    return $stmt;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $params = []): void
{
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params));
}

/** Tạo chuỗi "?, ?, ?" cho mệnh đề IN. */
function placeholders(array $items): string
{
    return implode(', ', array_fill(0, max(1, count($items)), '?'));
}
