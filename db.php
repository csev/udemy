<?php
/**
 * Shared SQLite helpers for the coupon site.
 * Included from index.php, crud.php, and text.php. Do not open this URL directly.
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

$SITE_TEXT_CONFIG = array(
    'display_name' => $DISPLAY_NAME,
    'udemy_homepage' => $UDEMY_HOMEPAGE,
);
if (isset($SITE_TITLE) && is_string($SITE_TITLE) && trim($SITE_TITLE) !== '') {
    $SITE_TEXT_CONFIG['site_title'] = trim($SITE_TITLE);
}
if (isset($INTRO) && is_string($INTRO) && trim($INTRO) !== '') {
    $SITE_TEXT_CONFIG['intro'] = trim($INTRO);
}

function coupons_possessive($name) {
    if (preg_match('/s$/i', $name)) {
        return $name . '’';
    }
    return $name . '’s';
}

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

function coupons_admin_script() {
    $here = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'crud.php');
    if ($here === '' || $here === 'db.php') {
        $here = 'crud.php';
    }
    return $here;
}

function coupons_require_admin() {
    global $ADMIN_PASSWORD, $SITE_HOST;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $here = coupons_admin_script();

    if (isset($_GET['logout'])) {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        coupons_clear_login_cookie();
        header('Location: ' . $here);
        exit;
    }

    if (coupons_is_admin()) {
        coupons_set_login_cookie();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        $got = (string) $_POST['password'];
        if (hash_equals($ADMIN_PASSWORD, $got)) {
            $_SESSION['coupons_ok'] = true;
            session_regenerate_id(true);
            coupons_set_login_cookie();
            header('Location: ' . $here);
            exit;
        }
        $error = 'Wrong password.';
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Coupon admin</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header>
    <div class="header-inner">
      <h1>Coupon admin</h1>
      <p><?php echo h($SITE_HOST); ?></p>
    </div>
  </header>
  <main>
    <h2>Password</h2>
    <?php if ($error !== ''): ?>
      <p class="flash error"><?php echo h($error); ?></p>
    <?php endif; ?>
    <form method="post" action="<?php echo h($here); ?>" class="login-form">
      <label>Password <input type="password" name="password" autofocus required></label>
      <button type="submit">View</button>
    </form>
  </main>
</body>
</html>
    <?php
    exit;
}

function coupons_db_has_current_schema(SQLite3 $db) {
    $courses = array();
    $info = $db->query('PRAGMA table_info(courses)');
    if ($info) {
        while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
            $courses[$col['name']] = true;
        }
    }
    $coupons = array();
    $info = $db->query('PRAGMA table_info(coupons)');
    if ($info) {
        while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
            $coupons[$col['name']] = true;
        }
    }
    return isset($courses['course_name'], $courses['url'], $courses['referral_code'], $courses['sort_order'])
        && isset($coupons['course_id'], $coupons['coupon_code'], $coupons['description'], $coupons['expires'])
        && !isset($coupons['course_name']);
}

function coupons_db_unlink() {
    foreach (array('', '-wal', '-shm', '-journal') as $suffix) {
        $path = COUPONS_DB_FILE . $suffix;
        if (is_file($path)) {
            @unlink($path);
        }
    }
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

    if (is_file(COUPONS_DB_FILE)) {
        $probe = new SQLite3(COUPONS_DB_FILE, SQLITE3_OPEN_READONLY);
        $ok = coupons_db_has_current_schema($probe);
        $probe->close();
        if (!$ok) {
            coupons_db_unlink();
        }
    }

    $db = new SQLite3(COUPONS_DB_FILE);
    $db->busyTimeout(3000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec(
        'CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            referral_code TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            coupon_code TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            expires TEXT NOT NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS site_text (
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );
    return $db;
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

function coupons_rebuild_url($url, $set = array()) {
    $url = trim((string) $url);
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return $url;
    }

    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $kept = array();
    foreach ($query as $name => $value) {
        $lower = strtolower((string) $name);
        if ($lower === 'referralcode' || $lower === 'couponcode') {
            continue;
        }
        $kept[$name] = $value;
    }
    foreach ($set as $name => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $kept[$name] = $value;
        }
    }

    $out = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
        . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
        . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . (isset($parts['path']) ? $parts['path'] : '')
        . ($kept ? '?' . http_build_query($kept) : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    return $out;
}

function course_parse_referral_url($url) {
    $url = trim((string) $url);
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $code = '';
    foreach ($query as $name => $value) {
        if (strtolower((string) $name) === 'referralcode') {
            $code = trim((string) $value);
            break;
        }
    }
    if ($code === '') {
        return null;
    }

    return array(
        'url' => coupons_rebuild_url($url),
        'referral_code' => $code,
    );
}

function course_parse_coupon_code($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $value)) {
        return $value;
    }

    $parts = parse_url($value);
    if ($parts === false || empty($parts['query'])) {
        return '';
    }
    $query = array();
    parse_str($parts['query'], $query);
    foreach ($query as $name => $item) {
        if (strtolower((string) $name) === 'couponcode') {
            return trim((string) $item);
        }
    }
    return '';
}

function course_referral_url($course) {
    return coupons_rebuild_url($course['url'], array('referralCode' => $course['referral_code']));
}

function course_coupon_url($course, $coupon) {
    return coupons_rebuild_url($course['url'], array('couponCode' => $coupon['coupon_code']));
}

function courses_all() {
    $db = coupons_db();
    $result = $db->query(
        'SELECT id, course_name, url, description, referral_code, sort_order
         FROM courses
         ORDER BY sort_order ASC, id ASC'
    );
    return coupons_fetch_all($result);
}

function courses_get($id) {
    $db = coupons_db();
    $stmt = $db->prepare(
        'SELECT id, course_name, url, description, referral_code, sort_order
         FROM courses WHERE id = :id'
    );
    $stmt->bindValue(':id', (int) $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row : null;
}

function coupons_for_course($course_id, $live_only = false) {
    $db = coupons_db();
    $sql = 'SELECT id, course_id, coupon_code, description, expires
            FROM coupons
            WHERE course_id = :course_id';
    if ($live_only) {
        $sql .= ' AND expires >= :today';
    }
    $sql .= ' ORDER BY expires ASC, id ASC';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':course_id', (int) $course_id, SQLITE3_INTEGER);
    if ($live_only) {
        $stmt->bindValue(':today', coupons_today(), SQLITE3_TEXT);
    }
    return coupons_fetch_all($stmt->execute());
}

function coupons_get($id) {
    $db = coupons_db();
    $stmt = $db->prepare(
        'SELECT id, course_id, coupon_code, description, expires
         FROM coupons WHERE id = :id'
    );
    $stmt->bindValue(':id', (int) $id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row : null;
}

function courses_public() {
    $courses = courses_all();
    foreach ($courses as &$course) {
        $course['coupons'] = coupons_for_course($course['id'], false);
        $course['live_coupons'] = array();
        foreach ($course['coupons'] as $coupon) {
            if (!coupons_is_expired($coupon['expires'])) {
                $course['live_coupons'][] = $coupon;
            }
        }
    }
    unset($course);
    return $courses;
}

function coupons_is_expired($expires) {
    return (string) $expires < coupons_today();
}

function courses_next_sort_order() {
    $db = coupons_db();
    return (int) $db->querySingle('SELECT COALESCE(MAX(sort_order), 0) FROM courses') + 1;
}

function courses_reorder($ids) {
    $known = array();
    foreach (courses_all() as $row) {
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
    $stmt = $db->prepare('UPDATE courses SET sort_order = :ord WHERE id = :id');
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

function site_text_fields() {
    global $DISPLAY_NAME, $SITE_TEXT_CONFIG;
    return array(
        'display_name' => array(
            'label' => 'Display name',
            'help' => 'Used for the avatar alt text, continue-link wording, and the default site title.',
            'type' => 'text',
            'max' => 100,
            'config' => isset($SITE_TEXT_CONFIG['display_name']) ? $SITE_TEXT_CONFIG['display_name'] : '',
            'default' => '',
        ),
        'site_title' => array(
            'label' => 'Site title',
            'help' => 'Browser tab and the heading at the top of the public page.',
            'type' => 'text',
            'max' => 200,
            'config' => isset($SITE_TEXT_CONFIG['site_title']) ? $SITE_TEXT_CONFIG['site_title'] : '',
            'default' => function () {
                global $DISPLAY_NAME;
                return $DISPLAY_NAME . ' on Udemy';
            },
        ),
        'udemy_homepage' => array(
            'label' => 'Udemy homepage',
            'help' => 'Used for the avatar, the continue link, and the redirect when there are no courses.',
            'type' => 'url',
            'max' => 1000,
            'config' => isset($SITE_TEXT_CONFIG['udemy_homepage']) ? $SITE_TEXT_CONFIG['udemy_homepage'] : '',
            'default' => '',
        ),
        'intro' => array(
            'label' => 'Welcome paragraph',
            'help' => 'Shown under Welcome on the public page.',
            'type' => 'textarea',
            'rows' => 5,
            'max' => 2000,
            'config' => isset($SITE_TEXT_CONFIG['intro']) ? $SITE_TEXT_CONFIG['intro'] : '',
            'default' => function () {
                global $DISPLAY_NAME;
                return 'Hi, I’m ' . $DISPLAY_NAME . '. You can continue to my Udemy homepage, or use one of the coupon links below while it is still valid.';
            },
        ),
    );
}

function site_text_default($field) {
    $default = $field['default'];
    if (is_callable($default)) {
        return (string) call_user_func($default);
    }
    return (string) $default;
}

function site_text_get($name) {
    $stmt = coupons_db()->prepare('SELECT value FROM site_text WHERE name = :name');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row || !isset($row['value'])) {
        return null;
    }
    return (string) $row['value'];
}

function site_text_resolve($name) {
    $fields = site_text_fields();
    if (!isset($fields[$name])) {
        return array(
            'value' => '',
            'source' => 'default',
            'stored' => false,
            'field' => null,
        );
    }
    $field = $fields[$name];
    $stored = site_text_get($name);
    if ($stored !== null && trim($stored) !== '') {
        return array(
            'value' => trim($stored),
            'source' => 'database',
            'stored' => true,
            'field' => $field,
        );
    }
    if ($field['config'] !== '') {
        return array(
            'value' => $field['config'],
            'source' => 'config',
            'stored' => false,
            'field' => $field,
        );
    }
    return array(
        'value' => site_text_default($field),
        'source' => 'default',
        'stored' => false,
        'field' => $field,
    );
}

function site_text($name) {
    $resolved = site_text_resolve($name);
    return $resolved['value'];
}

function site_text_save($name, $value) {
    $fields = site_text_fields();
    if (!isset($fields[$name])) {
        return false;
    }
    $max = isset($fields[$name]['max']) ? (int) $fields[$name]['max'] : 2000;
    $value = trim((string) $value);
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }
    if ($value === '') {
        return site_text_clear($name);
    }
    $stmt = coupons_db()->prepare(
        'INSERT OR REPLACE INTO site_text (name, value) VALUES (:name, :value)'
    );
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    $stmt->execute();
    return true;
}

function site_text_clear($name) {
    $stmt = coupons_db()->prepare('DELETE FROM site_text WHERE name = :name');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->execute();
    return true;
}

$DISPLAY_NAME = site_text('display_name');
$SITE_TITLE = site_text('site_title');
$INTRO = site_text('intro');
$UDEMY_HOMEPAGE = site_text('udemy_homepage');
$HOMEPAGE_LINK_TEXT = 'Continue to ' . coupons_possessive($DISPLAY_NAME) . ' Udemy homepage';
$META_DESCRIPTION = 'Current coupon codes for ' . coupons_possessive($DISPLAY_NAME) . ' Udemy courses.';

