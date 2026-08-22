<?php
require __DIR__ . '/db.php';

$is_admin = coupons_is_admin();

$coupons = coupons_active();
if (count($coupons) === 0) {
    header('Location: ' . $UDEMY_HOMEPAGE);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Current coupon codes for Dr. Chuck's Udemy courses.">
  <title>Dr. Chuck on Udemy</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header>
    <div class="header-inner">
      <h1>Dr. Chuck on Udemy</h1>
      <p>Current coupon codes</p>
    </div>
  </header>

  <main class="clearfix">
    <img
      class="hero-image"
      src="assets/chuck.jpg"
      alt="Dr. Chuck">

    <h2>Welcome</h2>
    <p>Hi, I’m Dr. Chuck. You can continue to my Udemy homepage, or use one of the coupon links below while it is still valid.</p>

    <ul class="actions" aria-label="Udemy homepage">
      <li><a href="<?php echo h($UDEMY_HOMEPAGE); ?>">Continue to Dr. Chuck’s Udemy homepage</a></li>
    </ul>

    <h2>Current coupons</h2>
    <ul class="coupons">
      <?php foreach ($coupons as $row): ?>
        <li>
          <a class="coupon-art<?php echo (!empty($row['warn_clicks']) && !$is_admin) ? ' coupon-launch' : ''; ?>" href="<?php echo h($row['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open coupon for <?php echo h($row['course_name']); ?>">
            <img src="assets/coupon.svg" alt="">
          </a>
          <div class="coupon-body">
            <a class="course<?php echo (!empty($row['warn_clicks']) && !$is_admin) ? ' coupon-launch' : ''; ?>" href="<?php echo h($row['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($row['course_name']); ?></a>
            <?php if (trim($row['description']) !== ''): ?>
              <p class="desc"><?php echo h($row['description']); ?></p>
            <?php endif; ?>
            <p class="meta">Expires <?php echo h(coupons_format_date($row['expires'])); ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </main>

  <footer>
    <p>udemy.dr-chuck.com</p>
    <?php if ($is_admin): ?>
      <p class="note">Logged in — click warnings are off so you can test links. <a href="crud.php">Edit coupons</a></p>
    <?php endif; ?>
  </footer>

  <div id="coupon-confirm" class="confirm-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="coupon-confirm-title">
    <div class="confirm-box">
      <h2 id="coupon-confirm-title">Ready to enroll?</h2>
      <p>Please only continue if you are ready to register. I do not fully understand Udemy’s coupon rules, but the discounted price often shows the first time you follow a link and may not show if you click the same link again later. Treat each click as a one-shot: open it, and finish registering if the price looks right.</p>
      <p class="confirm-actions">
        <button type="button" id="coupon-confirm-continue">Continue</button>
        <button type="button" class="secondary" id="coupon-confirm-cancel">Cancel</button>
      </p>
    </div>
  </div>
  <script>
    (function () {
      var modal = document.getElementById('coupon-confirm');
      var continueBtn = document.getElementById('coupon-confirm-continue');
      var cancelBtn = document.getElementById('coupon-confirm-cancel');
      var pendingUrl = '';
      var lastFocus = null;
      if (!modal || !continueBtn || !cancelBtn) return;

      function openModal(url, trigger) {
        pendingUrl = url;
        lastFocus = trigger;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        continueBtn.focus();
      }

      function closeModal() {
        pendingUrl = '';
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
      }

      document.querySelectorAll('.coupon-launch').forEach(function (link) {
        link.addEventListener('click', function (event) {
          event.preventDefault();
          openModal(link.href, link);
        });
      });

      continueBtn.addEventListener('click', function () {
        var url = pendingUrl;
        closeModal();
        if (url) window.open(url, '_blank', 'noopener,noreferrer');
      });

      cancelBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
      });
    })();
  </script>
</body>
</html>
