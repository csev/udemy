<?php
require __DIR__ . '/db.php';

coupons_require_admin();
$csrf = $_SESSION['csrf'];

function text_csrf_error() {
    $got = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['csrf'], $got)) {
        return 'Invalid form token. Please try again.';
    }
    return '';
}

$flash = '';
$flash_error = false;
$fields = site_text_fields();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_error = text_csrf_error();
    $posted = isset($_POST['values']) && is_array($_POST['values']) ? $_POST['values'] : array();

    if ($csrf_error !== '') {
        $flash = $csrf_error;
        $flash_error = true;
    } elseif (isset($_POST['reset']) && isset($fields[(string) $_POST['reset']])) {
        site_text_clear((string) $_POST['reset']);
        header('Location: text.php?reset=1');
        exit;
    } else {
        foreach ($fields as $name => $field) {
            $value = isset($posted[$name]) ? trim((string) $posted[$name]) : '';
            $type = isset($field['type']) ? $field['type'] : 'textarea';
            if ($type === 'url' && $value !== '') {
                $parts = parse_url($value);
                if ($parts === false || empty($parts['host']) || !preg_match('#^https?://#i', $value)) {
                    $flash = 'Enter a full http(s) URL for ' . $field['label'] . '.';
                    $flash_error = true;
                    break;
                }
            }
        }
        if (!$flash_error) {
            foreach ($fields as $name => $field) {
                $value = isset($posted[$name]) ? trim((string) $posted[$name]) : '';
                $fallback = $field['config'] !== '' ? $field['config'] : site_text_default($field);
                if ($value === '' || $value === $fallback) {
                    site_text_clear($name);
                } else {
                    site_text_save($name, $value);
                }
            }
            header('Location: text.php?saved=1');
            exit;
        }
    }
}

if (isset($_GET['saved'])) {
    $flash = 'Website text saved.';
} elseif (isset($_GET['reset'])) {
    $flash = 'Reset to default.';
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
  <title>Edit website text</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header>
    <div class="header-inner">
      <h1>Edit website text</h1>
      <p><a href="index.php">View public page</a> · <a href="crud.php">Edit courses</a> · <a href="utm-live.php">UTM Live</a> · <a href="text.php?logout=1">Log out</a></p>
    </div>
  </header>

  <main>
    <?php if ($flash !== ''): ?>
      <p class="flash<?php echo $flash_error ? ' error' : ''; ?>"><?php echo h($flash); ?></p>
    <?php endif; ?>

    <form method="post" action="text.php" class="coupon-form">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">

      <?php foreach ($fields as $name => $field): ?>
        <?php
          $resolved = site_text_resolve($name);
          $type = isset($field['type']) ? $field['type'] : 'textarea';
        ?>
        <label><?php echo h($field['label']); ?>
          <?php if ($type === 'url' || $type === 'text'): ?>
            <input type="<?php echo $type === 'url' ? 'url' : 'text'; ?>" name="values[<?php echo h($name); ?>]" maxlength="<?php echo (int) $field['max']; ?>" value="<?php echo h($resolved['value']); ?>">
          <?php else: ?>
            <textarea name="values[<?php echo h($name); ?>]" rows="<?php echo isset($field['rows']) ? (int) $field['rows'] : 4; ?>" maxlength="<?php echo (int) $field['max']; ?>"><?php echo h($resolved['value']); ?></textarea>
          <?php endif; ?>
        </label>
        <?php if (!empty($field['help']) || $resolved['stored']): ?>
          <p class="note">
            <?php if (!empty($field['help'])): ?>
              <?php echo h($field['help']); ?>
            <?php endif; ?>
            <?php if ($resolved['stored']): ?>
              <button type="submit" name="reset" value="<?php echo h($name); ?>" class="linkish">Reset to default</button>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      <?php endforeach; ?>

      <p class="form-actions">
        <button type="submit">Save</button>
      </p>
    </form>
  </main>

  <footer>
    <p><?php echo h($SITE_HOST); ?></p>
  </footer>
</body>
</html>
