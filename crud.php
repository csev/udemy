<?php
require __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function crud_require_password() {
    global $ADMIN_PASSWORD, $SITE_HOST;

    if (coupons_is_admin()) {
        coupons_set_login_cookie();
        return;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $got = isset($_POST['password']) ? (string) $_POST['password'] : '';
        if (hash_equals($ADMIN_PASSWORD, $got)) {
            $_SESSION['coupons_ok'] = true;
            session_regenerate_id(true);
            coupons_set_login_cookie();
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
      <p><?php echo h($SITE_HOST); ?></p>
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
    coupons_clear_login_cookie();
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
    'warn_clicks' => 0,
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
    } elseif ($action === 'reorder') {
        $raw = isset($_POST['order']) ? (string) $_POST['order'] : '';
        $ids = $raw === '' ? array() : explode(',', $raw);
        if (coupons_reorder($ids)) {
            header('Location: crud.php?reorder=1');
            exit;
        }
        $flash = 'Could not save the new order.';
        $flash_error = true;
    } elseif ($action === 'save') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $form['course_name'] = crud_clip(isset($_POST['course_name']) ? $_POST['course_name'] : '', 200);
        $form['url'] = crud_clip(isset($_POST['url']) ? $_POST['url'] : '', 1000);
        $form['description'] = crud_clip(isset($_POST['description']) ? $_POST['description'] : '', 500);
        $form['expires'] = crud_clip(isset($_POST['expires']) ? $_POST['expires'] : '', 10);
        $form['warn_clicks'] = !empty($_POST['warn_clicks']) ? 1 : 0;
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
                         expires = :expires, warn_clicks = :warn_clicks
                     WHERE id = :id'
                );
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO coupons (course_name, url, description, expires, sort_order, warn_clicks)
                     VALUES (:course_name, :url, :description, :expires, :sort_order, :warn_clicks)'
                );
                $stmt->bindValue(':sort_order', coupons_next_sort_order(), SQLITE3_INTEGER);
            }
            $stmt->bindValue(':course_name', $form['course_name'], SQLITE3_TEXT);
            $stmt->bindValue(':url', $form['url'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $form['description'], SQLITE3_TEXT);
            $stmt->bindValue(':expires', $form['expires'], SQLITE3_TEXT);
            $stmt->bindValue(':warn_clicks', (int) $form['warn_clicks'], SQLITE3_INTEGER);
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
      <p><a href="index.php">View public page</a> · <a href="utm-live.php">UTM Live</a> · <a href="crud.php?logout=1">Log out</a></p>
    </div>
  </header>

  <main>
    <?php if ($flash !== ''): ?>
      <p class="flash<?php echo $flash_error ? ' error' : ''; ?>"><?php echo h($flash); ?></p>
    <?php endif; ?>

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
                <?php if (!empty($row['warn_clicks'])): ?>
                  <div class="muted">click warning on</div>
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
      <?php if (count($rows) > 1): ?>
        <p class="form-actions reorder-toggle-wrap">
          <button type="button" class="secondary" id="reorder-toggle" aria-expanded="<?php echo isset($_GET['reorder']) ? 'true' : 'false'; ?>" aria-controls="reorder-panel">Reorder</button>
        </p>
        <div id="reorder-panel"<?php echo isset($_GET['reorder']) ? '' : ' hidden'; ?>>
          <p class="note">Drag the rows to change the order on the public page. The new order is saved when you drop an item.</p>
          <form method="post" action="crud.php" id="reorder-form">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="order" id="reorder-order" value="<?php echo h(implode(',', array_map(function ($row) { return (int) $row['id']; }, $rows))); ?>">
            <ul class="reorder-list" id="reorder-list">
              <?php foreach ($rows as $row): ?>
                <li draggable="true" data-id="<?php echo (int) $row['id']; ?>">
                  <span class="reorder-grip" aria-hidden="true">⋮⋮</span>
                  <span class="reorder-title"><?php echo h($row['course_name']); ?></span>
                  <span class="muted">Expires <?php echo h(coupons_format_date($row['expires'])); ?><?php echo coupons_is_expired($row['expires']) ? ' · expired' : ''; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <h2><?php echo $editing ? 'Edit coupon' : 'Add coupon'; ?></h2>

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
      <label class="check-label">
        <input type="checkbox" name="warn_clicks" value="1"<?php echo !empty($form['warn_clicks']) ? ' checked' : ''; ?>>
        Warn before opening: coupon clicks may be limited
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
    <p><?php echo h($SITE_HOST); ?></p>
  </footer>
  <script>
    (function () {
      var toggle = document.getElementById('reorder-toggle');
      var panel = document.getElementById('reorder-panel');
      var list = document.getElementById('reorder-list');
      var form = document.getElementById('reorder-form');
      var orderInput = document.getElementById('reorder-order');
      if (!toggle || !panel || !list || !form || !orderInput) return;

      var dragged = null;
      var startOrder = orderInput.value;

      function show() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
      }

      function hide() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      }

      toggle.addEventListener('click', function () {
        if (panel.hidden) show(); else hide();
      });

      function currentOrder() {
        return Array.prototype.map.call(list.querySelectorAll('li'), function (item) {
          return item.getAttribute('data-id');
        }).join(',');
      }

      function dragAfterElement(y) {
        var items = Array.prototype.filter.call(list.querySelectorAll('li'), function (item) {
          return item !== dragged;
        });
        var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        items.forEach(function (item) {
          var box = item.getBoundingClientRect();
          var offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) {
            closest = { offset: offset, element: item };
          }
        });
        return closest.element;
      }

      list.addEventListener('dragstart', function (event) {
        var item = event.target.closest('li');
        if (!item || !list.contains(item)) return;
        dragged = item;
        item.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.getAttribute('data-id'));
      });

      list.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('dragging');
        dragged = null;
        var next = currentOrder();
        if (next !== startOrder) {
          orderInput.value = next;
          form.submit();
        }
      });

      list.addEventListener('dragover', function (event) {
        event.preventDefault();
        if (!dragged) return;
        var after = dragAfterElement(event.clientY);
        if (after == null) {
          list.appendChild(dragged);
        } else {
          list.insertBefore(dragged, after);
        }
      });

      list.addEventListener('drop', function (event) {
        event.preventDefault();
      });
    })();
  </script>
</body>
</html>
