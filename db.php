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

if (!isset($DISPLAY_NAME) || !is_string($DISPLAY_NAME) || trim($DISPLAY_NAME) === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Set \$DISPLAY_NAME in config.php.\n";
    exit;
}
$DISPLAY_NAME = trim($DISPLAY_NAME);

if (!isset($SITE_HOST) || !is_string($SITE_HOST) || trim($SITE_HOST) === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Set \$SITE_HOST in config.php.\n";
    exit;
}
$SITE_HOST = trim($SITE_HOST);

if (!isset($SITE_TITLE) || !is_string($SITE_TITLE) || trim($SITE_TITLE) === '') {
    $SITE_TITLE = $DISPLAY_NAME . ' on Udemy';
} else {
    $SITE_TITLE = trim($SITE_TITLE);
}

if (!isset($INTRO) || !is_string($INTRO) || trim($INTRO) === '') {
    $INTRO = 'Hi, I’m ' . $DISPLAY_NAME . '. You can continue to my Udemy homepage, or use one of the coupon links below while it is still valid.';
} else {
    $INTRO = trim($INTRO);
}

function coupons_possessive($name) {
    if (preg_match('/s$/i', $name)) {
        return $name . '’';
    }
    return $name . '’s';
}

$HOMEPAGE_LINK_TEXT = 'Continue to ' . coupons_possessive($DISPLAY_NAME) . ' Udemy homepage';
$META_DESCRIPTION = 'Current coupon codes for ' . coupons_possessive($DISPLAY_NAME) . ' Udemy courses.';

if (!isset($HERO_IMAGE) || !is_string($HERO_IMAGE)) {
    $HERO_IMAGE = '';
} else {
    $HERO_IMAGE = trim($HERO_IMAGE);
}

define('COUPONS_DB_FILE', __DIR__ . '/coupons.sqlite');
define('COUPONS_ADMIN_COOKIE', 'udemy_admin');
define('COUPONS_ADMIN_COOKIE_DAYS', 30);

function coupons_cookie_secure() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    return false;
}

function coupons_login_cookie_value() {
    global $ADMIN_PASSWORD;
    return hash_hmac('sha256', 'udemy-crud', $ADMIN_PASSWORD);
}

function coupons_set_login_cookie() {
    $value = coupons_login_cookie_value();
    $expires = time() + (COUPONS_ADMIN_COOKIE_DAYS * 86400);
    $secure = coupons_cookie_secure();
    if (PHP_VERSION_ID >= 70300) {
        setcookie(COUPONS_ADMIN_COOKIE, $value, array(
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        setcookie(COUPONS_ADMIN_COOKIE, $value, $expires, '/', '', $secure, true);
    }
    $_COOKIE[COUPONS_ADMIN_COOKIE] = $value;
}

function coupons_clear_login_cookie() {
    $secure = coupons_cookie_secure();
    if (PHP_VERSION_ID >= 70300) {
        setcookie(COUPONS_ADMIN_COOKIE, '', array(
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        setcookie(COUPONS_ADMIN_COOKIE, '', time() - 3600, '/', '', $secure, true);
    }
    unset($_COOKIE[COUPONS_ADMIN_COOKIE]);
}

function coupons_is_admin() {
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['coupons_ok'])) {
        return true;
    }
    $got = isset($_COOKIE[COUPONS_ADMIN_COOKIE]) ? (string) $_COOKIE[COUPONS_ADMIN_COOKIE] : '';
    if ($got === '' || !hash_equals(coupons_login_cookie_value(), $got)) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['coupons_ok'] = true;
    }
    return true;
}

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
            expires TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            warn_clicks INTEGER NOT NULL DEFAULT 0
        )'
    );
    coupons_migrate_drop_code($db);
    coupons_migrate_sort_order($db);
    coupons_migrate_warn_clicks($db);
    return $db;
}

function coupons_has_column(SQLite3 $db, $name) {
    $info = $db->query('PRAGMA table_info(coupons)');
    while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === $name) {
            return true;
        }
    }
    return false;
}

function coupons_migrate_drop_code(SQLite3 $db) {
    if (!coupons_has_column($db, 'coupon_code')) {
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

function coupons_migrate_sort_order(SQLite3 $db) {
    if (!coupons_has_column($db, 'sort_order')) {
        $db->exec('ALTER TABLE coupons ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0');
    }

    $count = (int) $db->querySingle('SELECT COUNT(*) FROM coupons');
    $max = (int) $db->querySingle('SELECT COALESCE(MAX(sort_order), 0) FROM coupons');
    if ($count === 0 || $max > 0) {
        return;
    }

    $result = $db->query(
        'SELECT id FROM coupons ORDER BY expires ASC, course_name ASC, id ASC'
    );
    $stmt = $db->prepare('UPDATE coupons SET sort_order = :ord WHERE id = :id');
    $ord = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ord++;
        $stmt->bindValue(':ord', $ord, SQLITE3_INTEGER);
        $stmt->bindValue(':id', (int) $row['id'], SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }
}

function coupons_migrate_warn_clicks(SQLite3 $db) {
    if (!coupons_has_column($db, 'warn_clicks')) {
        $db->exec('ALTER TABLE coupons ADD COLUMN warn_clicks INTEGER NOT NULL DEFAULT 0');
    }
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
        'SELECT id, course_name, url, description, expires, sort_order, warn_clicks
         FROM coupons
         WHERE expires >= :today
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->bindValue(':today', coupons_today(), SQLITE3_TEXT);
    return coupons_fetch_all($stmt->execute());
}

function coupons_all() {
    $db = coupons_db();
    $result = $db->query(
        'SELECT id, course_name, url, description, expires, sort_order, warn_clicks
         FROM coupons
         ORDER BY sort_order ASC, id ASC'
    );
    return coupons_fetch_all($result);
}

function coupons_get($id) {
    $db = coupons_db();
    $stmt = $db->prepare(
        'SELECT id, course_name, url, description, expires, sort_order, warn_clicks
         FROM coupons WHERE id = :id'
    );
    $stmt->bindValue(':id', (int) $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row : null;
}

function coupons_is_expired($expires) {
    return (string) $expires < coupons_today();
}

function coupons_next_sort_order() {
    $db = coupons_db();
    return (int) $db->querySingle('SELECT COALESCE(MAX(sort_order), 0) FROM coupons') + 1;
}

function coupons_reorder($ids) {
    $known = array();
    foreach (coupons_all() as $row) {
        $known[(int) $row['id']] = true;
    }

    $clean = array();
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($known[$id]) && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
    }
    if (count($clean) !== count($known)) {
        return false;
    }

    $db = coupons_db();
    $db->exec('BEGIN IMMEDIATE');
    $stmt = $db->prepare('UPDATE coupons SET sort_order = :ord WHERE id = :id');
    $ord = 0;
    foreach ($clean as $id) {
        $ord++;
        $stmt->bindValue(':ord', $ord, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }
    $db->exec('COMMIT');
    return true;
}

function coupons_format_date($ymd) {
    $dt = DateTime::createFromFormat('Y-m-d', (string) $ymd);
    if (!$dt) {
        return (string) $ymd;
    }
    return $dt->format('F j, Y');
}
