<?php
require __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function crud_require_password() {
    global $ADMIN_PASSWORD;

    if (!empty($_SESSION['coupons_ok'])) {
        return;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $got = isset($_POST['password']) ? (string) $_POST['password'] : '';
        if (hash_equals($ADMIN_PASSWORD, $got)) {
            $_SESSION['coupons_ok'] = true;
            session_regenerate_id(true);
            header('Location: crud.php');
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
      <p>udemy.dr-chuck.com</p>
    </div>
  </header>
  <main>
    <h2>Password</h2>
    <?php if ($error !== ''): ?>
      <p class="flash error"><?php echo h($error); ?></p>
    <?php endif; ?>
    <form method="post" action="crud.php" class="login-form">
      <label>Password <input type="password" name="password" autofocus required></label>
      <button type="submit">View</button>
    </form>
  </main>
</body>
</html>
    <?php
    exit;
}

if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: crud.php');
    exit;
}

crud_require_password();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function crud_check_csrf() {
    $got = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['csrf'], $got)) {
        return 'Invalid form token. Please try again.';
    }
    return '';
}

function crud_clip($value, $max) {
    $value = trim((string) $value);
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }
    return $value;
}

$flash = '';
$flash_error = false;
$form = array(
    'id' => '',
    'course_name' => '',
    'url' => '',
    'description' => '',
    'expires' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_error = crud_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($csrf_error !== '') {
        $flash = $csrf_error;
        $flash_error = true;
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = coupons_db()->prepare('DELETE FROM coupons WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            $flash = 'Coupon deleted.';
        }
        header('Location: crud.php');
        exit;
    } elseif ($action === 'save') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $form['course_name'] = crud_clip(isset($_POST['course_name']) ? $_POST['course_name'] : '', 200);
        $form['url'] = crud_clip(isset($_POST['url']) ? $_POST['url'] : '', 1000);
        $form['description'] = crud_clip(isset($_POST['description']) ? $_POST['description'] : '', 500);
        $form['expires'] = crud_clip(isset($_POST['expires']) ? $_POST['expires'] : '', 10);
        if ($id > 0) {
            $form['id'] = (string) $id;
        }

        $errors = array();
        if ($form['course_name'] === '') {
            $errors[] = 'Course name is required.';
        }
        if ($form['url'] === '') {
            $errors[] = 'Link is required.';
        }
        if ($form['expires'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['expires'])) {
            $errors[] = 'Expiration date is required.';
        }

        if ($errors) {
            $flash = implode(' ', $errors);
            $flash_error = true;
        } else {
            $db = coupons_db();
            if ($id > 0) {
                $stmt = $db->prepare(
                    'UPDATE coupons
                     SET course_name = :course_name, url = :url, description = :description,
                         expires = :expires
                     WHERE id = :id'
                );
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO coupons (course_name, url, description, expires)
                     VALUES (:course_name, :url, :description, :expires)'
                );
            }
            $stmt->bindValue(':course_name', $form['course_name'], SQLITE3_TEXT);
            $stmt->bindValue(':url', $form['url'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $form['description'], SQLITE3_TEXT);
            $stmt->bindValue(':expires', $form['expires'], SQLITE3_TEXT);
            $stmt->execute();
            header('Location: crud.php');
            exit;
        }
    }
} elseif (isset($_GET['edit'])) {
    $row = coupons_get($_GET['edit']);
    if ($row) {
        $form = $row;
    } else {
        $flash = 'Coupon not found.';
        $flash_error = true;
    }
}

$rows = coupons_all();
$editing = ($form['id'] !== '');

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
      <p><a href="index.php">View public page</a> · <a href="crud.php?logout=1">Log out</a></p>
    </div>
  </header>

  <main>
    <h2>All coupons</h2>
    <?php if (count($rows) === 0): ?>
      <p>No coupons yet. The public page will redirect to the Udemy homepage until you add one.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <tr>
            <th>Course</th>
            <th>Expires</th>
            <th></th>
          </tr>
          <?php foreach ($rows as $row): ?>
            <tr<?php echo coupons_is_expired($row['expires']) ? ' class="expired"' : ''; ?>>
              <td>
                <a href="<?php echo h($row['url']); ?>"><?php echo h($row['course_name']); ?></a>
                <?php if (trim($row['description']) !== ''): ?>
                  <div class="muted"><?php echo h($row['description']); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php echo h(coupons_format_date($row['expires'])); ?>
                <?php if (coupons_is_expired($row['expires'])): ?>
                  <div class="muted">expired</div>
                <?php endif; ?>
              </td>
              <td class="row-actions">
                <a href="crud.php?edit=<?php echo (int) $row['id']; ?>">Edit</a>
                <form method="post" action="crud.php" onsubmit="return confirm('Delete this coupon?');">
                  <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" class="linkish">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>

    <h2><?php echo $editing ? 'Edit coupon' : 'Add coupon'; ?></h2>
    <?php if ($flash !== ''): ?>
      <p class="flash<?php echo $flash_error ? ' error' : ''; ?>"><?php echo h($flash); ?></p>
    <?php endif; ?>

    <form method="post" action="crud.php" class="coupon-form">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?php echo h($form['id']); ?>">

      <label>Course name
        <input type="text" name="course_name" maxlength="200" required value="<?php echo h($form['course_name']); ?>">
      </label>
      <label>Link
        <input type="url" name="url" maxlength="1000" required placeholder="https://www.udemy.com/course/...?couponCode=..." value="<?php echo h($form['url']); ?>">
      </label>
      <p class="note">Paste the full course URL, including the coupon code.</p>
      <label>Short description
        <input type="text" name="description" maxlength="500" value="<?php echo h($form['description']); ?>">
      </label>
      <label>Expires
        <input type="date" name="expires" required value="<?php echo h($form['expires']); ?>">
      </label>
      <p class="form-actions">
        <button type="submit"><?php echo $editing ? 'Save changes' : 'Add coupon'; ?></button>
        <?php if ($editing): ?>
          <a href="crud.php">Cancel</a>
        <?php endif; ?>
      </p>
    </form>
  </main>

  <footer>
    <p>udemy.dr-chuck.com</p>
  </footer>
</body>
</html>
