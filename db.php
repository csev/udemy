<?php
/**
 * Shared SQLite helpers for the coupon site.
 * Included from index.php and crud.php. Do not open this URL directly.
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$config_file = __DIR__ . '/config.php';
if (!is_readable($config_file)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing config.php. Copy config.sample.php to config.php and edit.\n";
    exit;
}

require $config_file;

if (!isset($ADMIN_PASSWORD) || !is_string($ADMIN_PASSWORD) || trim($ADMIN_PASSWORD) === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Set \$ADMIN_PASSWORD in config.php.\n";
    exit;
}

if (!isset($UDEMY_HOMEPAGE) || !is_string($UDEMY_HOMEPAGE) || trim($UDEMY_HOMEPAGE) === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Set \$UDEMY_HOMEPAGE in config.php.\n";
    exit;
}

$ADMIN_PASSWORD = trim($ADMIN_PASSWORD);
$UDEMY_HOMEPAGE = trim($UDEMY_HOMEPAGE);

define('COUPONS_DB_FILE', __DIR__ . '/coupons.sqlite');

function coupons_db() {
    static $db = null;
    if ($db instanceof SQLite3) {
        return $db;
    }

    if (!class_exists('SQLite3')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "PHP SQLite3 extension is required.\n";
        exit;
    }

    $db = new SQLite3(COUPONS_DB_FILE);
    $db->busyTimeout(3000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec(
        'CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            expires TEXT NOT NULL
        )'
    );
    coupons_migrate_drop_code($db);
    return $db;
}

function coupons_migrate_drop_code(SQLite3 $db) {
    $info = $db->query('PRAGMA table_info(coupons)');
    $has_code = false;
    while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'coupon_code') {
            $has_code = true;
            break;
        }
    }
    if (!$has_code) {
        return;
    }

    $db->exec('BEGIN IMMEDIATE');
    $db->exec(
        'CREATE TABLE coupons_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            expires TEXT NOT NULL
        )'
    );
    $db->exec(
        'INSERT INTO coupons_new (id, course_name, url, description, expires)
         SELECT id, course_name, url, description, expires FROM coupons'
    );
    $db->exec('DROP TABLE coupons');
    $db->exec('ALTER TABLE coupons_new RENAME TO coupons');
    $db->exec('COMMIT');
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function coupons_today() {
    return date('Y-m-d');
}

function coupons_fetch_all($result) {
    $rows = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function coupons_active() {
    $db = coupons_db();
    $stmt = $db->prepare(
        'SELECT id, course_name, url, description, expires
         FROM coupons
         WHERE expires >= :today
         ORDER BY expires ASC, course_name ASC, id ASC'
    );
    $stmt->bindValue(':today', coupons_today(), SQLITE3_TEXT);
    return coupons_fetch_all($stmt->execute());
}

function coupons_all() {
    $db = coupons_db();
    $result = $db->query(
        'SELECT id, course_name, url, description, expires
         FROM coupons
         ORDER BY expires DESC, course_name ASC, id ASC'
    );
    return coupons_fetch_all($result);
}

function coupons_get($id) {
    $db = coupons_db();
    $stmt = $db->prepare(
        'SELECT id, course_name, url, description, expires
         FROM coupons WHERE id = :id'
    );
    $stmt->bindValue(':id', (int) $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row : null;
}

function coupons_is_expired($expires) {
    return (string) $expires < coupons_today();
}

function coupons_format_date($ymd) {
    $dt = DateTime::createFromFormat('Y-m-d', (string) $ymd);
    if (!$dt) {
        return (string) $ymd;
    }
    return $dt->format('F j, Y');
}
