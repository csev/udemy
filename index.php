<?php
require __DIR__ . '/db.php';

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
    <p>Please only click a coupon link when you are ready to enroll. I do not fully understand Udemy’s coupon rules, but the discounted price often shows the first time you follow a link and may not show if you click the same link again later. Treat each click as a one-shot: open it, and finish registering if the price looks right.</p>

    <ul class="actions" aria-label="Udemy homepage">
      <li><a href="<?php echo h($UDEMY_HOMEPAGE); ?>">Continue to Dr. Chuck’s Udemy homepage</a></li>
    </ul>

    <h2>Current coupons</h2>
    <ul class="coupons">
      <?php foreach ($coupons as $row): ?>
        <li>
          <a class="coupon-art" href="<?php echo h($row['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open coupon for <?php echo h($row['course_name']); ?>">
            <img src="assets/coupon.svg" alt="">
          </a>
          <div class="coupon-body">
            <a class="course" href="<?php echo h($row['url']); ?>"><?php echo h($row['course_name']); ?></a>
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
  </footer>
</body>
</html>
