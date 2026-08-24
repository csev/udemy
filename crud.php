<?php
require __DIR__ . '/db.php';

coupons_require_admin();
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
$course_form = array(
    'id' => '',
    'course_name' => '',
    'url' => '',
    'description' => '',
    'referral_code' => '',
);
$coupon_form = array(
    'id' => '',
    'course_id' => '',
    'coupon_code' => '',
    'description' => '',
    'expires' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_error = crud_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($csrf_error !== '') {
        $flash = $csrf_error;
        $flash_error = true;
    } elseif ($action === 'delete_course') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = coupons_db()->prepare('DELETE FROM courses WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: crud.php');
        exit;
    } elseif ($action === 'delete_coupon') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = coupons_db()->prepare('DELETE FROM coupons WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: crud.php');
        exit;
    } elseif ($action === 'reorder') {
        $raw = isset($_POST['order']) ? (string) $_POST['order'] : '';
        $ids = $raw === '' ? array() : explode(',', $raw);
        if (courses_reorder($ids)) {
            header('Location: crud.php?reorder=1');
            exit;
        }
        $flash = 'Could not save the new order.';
        $flash_error = true;
    } elseif ($action === 'save_course') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $course_form['course_name'] = crud_clip(isset($_POST['course_name']) ? $_POST['course_name'] : '', 200);
        $course_form['url'] = crud_clip(isset($_POST['url']) ? $_POST['url'] : '', 1000);
        $course_form['description'] = crud_clip(isset($_POST['description']) ? $_POST['description'] : '', 500);
        if ($id > 0) {
            $course_form['id'] = (string) $id;
        }

        $parsed = course_parse_referral_url($course_form['url']);
        $errors = array();
        if ($course_form['course_name'] === '') {
            $errors[] = 'Course name is required.';
        }
        if ($course_form['url'] === '') {
            $errors[] = 'Course URL is required.';
        } elseif (!$parsed) {
            $errors[] = 'Paste the Udemy course URL that includes ?referralCode=...';
        }

        if ($errors) {
            $flash = implode(' ', $errors);
            $flash_error = true;
        } else {
            $db = coupons_db();
            if ($id > 0) {
                $stmt = $db->prepare(
                    'UPDATE courses
                     SET course_name = :course_name, url = :url, description = :description,
                         referral_code = :referral_code
                     WHERE id = :id'
                );
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO courses (course_name, url, description, referral_code, sort_order)
                     VALUES (:course_name, :url, :description, :referral_code, :sort_order)'
                );
                $stmt->bindValue(':sort_order', courses_next_sort_order(), SQLITE3_INTEGER);
            }
            $stmt->bindValue(':course_name', $course_form['course_name'], SQLITE3_TEXT);
            $stmt->bindValue(':url', $parsed['url'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $course_form['description'], SQLITE3_TEXT);
            $stmt->bindValue(':referral_code', $parsed['referral_code'], SQLITE3_TEXT);
            $stmt->execute();
            header('Location: crud.php');
            exit;
        }
    } elseif ($action === 'save_coupon') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $coupon_form['course_id'] = isset($_POST['course_id']) ? (string) ((int) $_POST['course_id']) : '';
        $coupon_form['coupon_code'] = course_parse_coupon_code(crud_clip(isset($_POST['coupon_code']) ? $_POST['coupon_code'] : '', 1000));
        $coupon_form['description'] = crud_clip(isset($_POST['coupon_description']) ? $_POST['coupon_description'] : '', 500);
        $coupon_form['expires'] = crud_clip(isset($_POST['expires']) ? $_POST['expires'] : '', 10);
        if ($id > 0) {
            $coupon_form['id'] = (string) $id;
        }

        $errors = array();
        $course = courses_get((int) $coupon_form['course_id']);
        if (!$course) {
            $errors[] = 'Select a course.';
        }
        if ($coupon_form['coupon_code'] === '') {
            $errors[] = 'Coupon code is required.';
        }
        if ($coupon_form['expires'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $coupon_form['expires'])) {
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
                     SET course_id = :course_id, coupon_code = :coupon_code,
                         description = :description, expires = :expires
                     WHERE id = :id'
                );
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO coupons (course_id, coupon_code, description, expires)
                     VALUES (:course_id, :coupon_code, :description, :expires)'
                );
            }
            $stmt->bindValue(':course_id', (int) $coupon_form['course_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':coupon_code', $coupon_form['coupon_code'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $coupon_form['description'], SQLITE3_TEXT);
            $stmt->bindValue(':expires', $coupon_form['expires'], SQLITE3_TEXT);
            $stmt->execute();
            header('Location: crud.php');
            exit;
        }
    }
} elseif (isset($_GET['edit_course'])) {
    $row = courses_get($_GET['edit_course']);
    if ($row) {
        $course_form = $row;
        $course_form['url'] = course_referral_url($row);
    } else {
        $flash = 'Course not found.';
        $flash_error = true;
    }
} elseif (isset($_GET['edit_coupon'])) {
    $row = coupons_get($_GET['edit_coupon']);
    if ($row) {
        $coupon_form = $row;
    } else {
        $flash = 'Coupon not found.';
        $flash_error = true;
    }
} elseif (isset($_GET['add_coupon'])) {
    $course = courses_get($_GET['add_coupon']);
    if ($course) {
        $coupon_form['course_id'] = (string) (int) $course['id'];
    }
}

$courses = courses_public();
$editing_course = ($course_form['id'] !== '');
$editing_coupon = ($coupon_form['id'] !== '');

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
      <p><a href="index.php">View public page</a> · <a href="text.php">Edit website text</a> · <a href="utm-live.php">UTM Live</a> · <a href="crud.php?logout=1">Log out</a></p>
    </div>
  </header>

  <main>
    <?php if ($flash !== ''): ?>
      <p class="flash<?php echo $flash_error ? ' error' : ''; ?>"><?php echo h($flash); ?></p>
    <?php endif; ?>

    <h2>Courses</h2>
    <?php if (count($courses) === 0): ?>
      <p>No courses yet. The public page will redirect to the Udemy homepage until you add one.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <tr>
            <th>Course</th>
            <th>Coupons</th>
            <th></th>
          </tr>
          <?php foreach ($courses as $course): ?>
            <tr>
              <td>
                <a href="<?php echo h(course_referral_url($course)); ?>"><?php echo h($course['course_name']); ?></a>
                <?php if (trim($course['description']) !== ''): ?>
                  <div class="muted"><?php echo h($course['description']); ?></div>
                <?php endif; ?>
                <div class="muted">Referral <code><?php echo h($course['referral_code']); ?></code></div>
              </td>
              <td>
                <?php if (count($course['coupons']) === 0): ?>
                  <div class="muted">None yet</div>
                <?php else: ?>
                  <?php foreach ($course['coupons'] as $coupon): ?>
                    <div<?php echo coupons_is_expired($coupon['expires']) ? ' class="muted"' : ''; ?>>
                      <code><?php echo h($coupon['coupon_code']); ?></code>
                      · <?php echo h(coupons_format_date($coupon['expires'])); ?>
                      <?php if (coupons_is_expired($coupon['expires'])): ?>
                        · expired
                      <?php endif; ?>
                      · <a href="crud.php?edit_coupon=<?php echo (int) $coupon['id']; ?>#coupon-form">Edit</a>
                      <form method="post" action="crud.php" class="inline-form" onsubmit="return confirm('Delete this coupon?');">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="delete_coupon">
                        <input type="hidden" name="id" value="<?php echo (int) $coupon['id']; ?>">
                        <button type="submit" class="linkish">Delete</button>
                      </form>
                      <?php if (trim($coupon['description']) !== ''): ?>
                        <div class="muted"><?php echo h($coupon['description']); ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
                <div><a href="crud.php?add_coupon=<?php echo (int) $course['id']; ?>#coupon-form">Add coupon</a></div>
              </td>
              <td class="row-actions">
                <a href="crud.php?edit_course=<?php echo (int) $course['id']; ?>">Edit</a>
                <form method="post" action="crud.php" onsubmit="return confirm('Delete this course and its coupons?');">
                  <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                  <input type="hidden" name="action" value="delete_course">
                  <input type="hidden" name="id" value="<?php echo (int) $course['id']; ?>">
                  <button type="submit" class="linkish">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php if (count($courses) > 1): ?>
        <p class="form-actions reorder-toggle-wrap">
          <button type="button" class="secondary" id="reorder-toggle" aria-expanded="<?php echo isset($_GET['reorder']) ? 'true' : 'false'; ?>" aria-controls="reorder-panel">Reorder</button>
        </p>
        <div id="reorder-panel"<?php echo isset($_GET['reorder']) ? '' : ' hidden'; ?>>
          <p class="note">Drag the courses to change the order on the public page. The new order is saved when you drop an item.</p>
          <form method="post" action="crud.php" id="reorder-form">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="order" id="reorder-order" value="<?php echo h(implode(',', array_map(function ($row) { return (int) $row['id']; }, $courses))); ?>">
            <ul class="reorder-list" id="reorder-list">
              <?php foreach ($courses as $course): ?>
                <li draggable="true" data-id="<?php echo (int) $course['id']; ?>">
                  <span class="reorder-grip" aria-hidden="true">⋮⋮</span>
                  <span class="reorder-title"><?php echo h($course['course_name']); ?></span>
                  <span class="muted"><?php echo count($course['live_coupons']); ?> live coupon<?php echo count($course['live_coupons']) === 1 ? '' : 's'; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <h2><?php echo $editing_course ? 'Edit course' : 'Add course'; ?></h2>
    <form method="post" action="crud.php" class="coupon-form">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="id" value="<?php echo h($course_form['id']); ?>">

      <label>Course name
        <input type="text" name="course_name" maxlength="200" required value="<?php echo h($course_form['course_name']); ?>">
      </label>
      <label>Course URL
        <input type="url" name="url" maxlength="1000" required placeholder="https://www.udemy.com/course/...?referralCode=..." value="<?php echo h($course_form['url']); ?>">
      </label>
      <p class="note">Paste the course URL Udemy gives you, including <code>?referralCode=...</code>. The referral code is stored from that link.</p>
      <label>Short description
        <input type="text" name="description" maxlength="500" value="<?php echo h($course_form['description']); ?>">
      </label>
      <p class="form-actions">
        <button type="submit"><?php echo $editing_course ? 'Save course' : 'Add course'; ?></button>
        <?php if ($editing_course): ?>
          <a href="crud.php">Cancel</a>
        <?php endif; ?>
      </p>
    </form>

    <h2 id="coupon-form"><?php echo $editing_coupon ? 'Edit coupon' : 'Add coupon'; ?></h2>
    <?php if (count($courses) === 0): ?>
      <p>Add a course first, then you can attach coupon codes to it.</p>
    <?php else: ?>
      <form method="post" action="crud.php" class="coupon-form">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="action" value="save_coupon">
        <input type="hidden" name="id" value="<?php echo h($coupon_form['id']); ?>">

        <label>Course
          <select name="course_id" required>
            <option value="">Select a course</option>
            <?php foreach ($courses as $course): ?>
              <option value="<?php echo (int) $course['id']; ?>"<?php echo (string) $course['id'] === (string) $coupon_form['course_id'] ? ' selected' : ''; ?>><?php echo h($course['course_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Coupon code
          <input type="text" name="coupon_code" maxlength="1000" required value="<?php echo h($coupon_form['coupon_code']); ?>">
        </label>
        <p class="note">Paste the coupon code only. The public coupon link uses the course URL with <code>couponCode</code> instead of <code>referralCode</code>.</p>
        <label>Expires
          <input type="date" name="expires" required value="<?php echo h($coupon_form['expires']); ?>">
        </label>
        <label>Short description
          <input type="text" name="coupon_description" maxlength="500" value="<?php echo h($coupon_form['description']); ?>" placeholder="Optional, shown under the code and date">
        </label>
        <p class="form-actions">
          <button type="submit"><?php echo $editing_coupon ? 'Save coupon' : 'Add coupon'; ?></button>
          <?php if ($editing_coupon): ?>
            <a href="crud.php">Cancel</a>
          <?php endif; ?>
        </p>
      </form>
    <?php endif; ?>
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
