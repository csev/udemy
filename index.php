<?php
require __DIR__ . '/utm-live.php';
require __DIR__ . '/db.php';

$is_admin = coupons_is_admin();

$courses = courses_public();
if (count($courses) === 0) {
    header('Location: ' . $UDEMY_HOMEPAGE);
    exit;
}

$courses_with_coupons = array();
$courses_without_coupons = array();
foreach ($courses as $course) {
    if (count($course['live_coupons']) > 0) {
        $courses_with_coupons[] = $course;
    } else {
        $courses_without_coupons[] = $course;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?php echo h($META_DESCRIPTION); ?>">
  <title><?php echo h($SITE_TITLE); ?></title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="styles.css">
  <script>
    (function () {
      var url = new URL(window.location.href);
      if (url.searchParams.has('utm') && !url.searchParams.has('utm_source')) {
        url.searchParams.set('utm_source', url.searchParams.get('utm'));
        window.history.replaceState({}, '', url);
      }
    })();
  </script>
</head>
<body>
  <header>
    <div class="header-inner">
      <h1><?php echo h($SITE_TITLE); ?></h1>
      <p>Courses and coupon codes</p>
    </div>
  </header>

  <main class="clearfix">
    <?php if ($HERO_IMAGE !== ''): ?>
    <a class="hero-image" href="<?php echo h($UDEMY_HOMEPAGE); ?>">
      <img
        src="<?php echo h($HERO_IMAGE); ?>"
        alt="<?php echo h($DISPLAY_NAME); ?>">
    </a>
    <?php endif; ?>

    <h2>Welcome</h2>
    <p><?php echo h($INTRO); ?></p>

    <ul class="actions" aria-label="Udemy homepage">
      <li><a href="<?php echo h($UDEMY_HOMEPAGE); ?>"><?php echo h($HOMEPAGE_LINK_TEXT); ?></a></li>
    </ul>

    <?php if (count($courses_with_coupons) > 0): ?>
    <h2>Courses with coupons</h2>
    <ul class="coupons">
      <?php foreach ($courses_with_coupons as $course): ?>
        <li>
          <div class="coupon-body">
            <span class="course"><?php echo h($course['course_name']); ?></span>
            <?php if (trim($course['description']) !== ''): ?>
              <p class="desc"><?php echo h($course['description']); ?></p>
            <?php endif; ?>
            <ul class="coupon-offers">
              <?php foreach ($course['live_coupons'] as $coupon): ?>
                <li>
                  <a
                    class="coupon-offer coupon-launch"
                    href="<?php echo h(course_coupon_url($course, $coupon)); ?>"
                    data-coupon-url="<?php echo h(course_coupon_url($course, $coupon)); ?>"
                    data-referral-url="<?php echo h(course_referral_url($course)); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Open coupon <?php echo h($coupon['coupon_code']); ?> for <?php echo h($course['course_name']); ?>">
                    <span class="coupon-offer-line">
                      <span class="coupon-art">
                        <img src="assets/coupon.svg" alt="">
                      </span>
                      <span class="meta">
                        <code><?php echo h($coupon['coupon_code']); ?></code>
                        · Expires <?php echo h(coupons_format_date($coupon['expires'])); ?>
                      </span>
                    </span>
                    <?php if (trim($coupon['description']) !== ''): ?>
                      <span class="desc"><?php echo h($coupon['description']); ?></span>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (count($courses_without_coupons) > 0): ?>
    <h2><?php echo count($courses_with_coupons) > 0 ? 'Other courses' : 'Courses'; ?></h2>
    <ul class="coupons">
      <?php foreach ($courses_without_coupons as $course): ?>
        <li>
          <div class="coupon-body">
            <a class="course" href="<?php echo h(course_referral_url($course)); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($course['course_name']); ?></a>
            <?php if (trim($course['description']) !== ''): ?>
              <p class="desc"><?php echo h($course['description']); ?></p>
            <?php endif; ?>
            <p class="meta">Opens with a referral link — no coupon on this course right now.</p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </main>

  <footer>
    <p><?php echo h($SITE_HOST); ?></p>
    <?php if ($is_admin): ?>
      <p class="note"><a href="crud.php">Edit courses</a> · <a href="text.php">Edit website text</a></p>
    <?php endif; ?>
  </footer>

  <div id="coupon-confirm" class="confirm-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="coupon-confirm-title">
    <div class="confirm-box">
      <h2 id="coupon-confirm-title">Use this coupon?</h2>
      <p>Udemy often applies a coupon only the first time you follow the coupon link. If you are ready to enroll, continue. If you just want to look around first, explore the course with a referral link instead and then come back and use the coupon link.</p>
      <p class="confirm-actions">
        <button type="button" id="coupon-confirm-continue">Use this coupon</button>
        <button type="button" class="secondary" id="coupon-confirm-explore">Explore the course first</button>
        <button type="button" class="secondary" id="coupon-confirm-cancel">Cancel</button>
      </p>
    </div>
  </div>
  <script>
    (function () {
      var modal = document.getElementById('coupon-confirm');
      var continueBtn = document.getElementById('coupon-confirm-continue');
      var exploreBtn = document.getElementById('coupon-confirm-explore');
      var cancelBtn = document.getElementById('coupon-confirm-cancel');
      var pendingCoupon = '';
      var pendingReferral = '';
      var lastFocus = null;
      if (!modal || !continueBtn || !exploreBtn || !cancelBtn) return;

      function openUrl(url) {
        if (url) window.open(url, '_blank', 'noopener,noreferrer');
      }

      function openModal(couponUrl, referralUrl, trigger) {
        pendingCoupon = couponUrl;
        pendingReferral = referralUrl;
        lastFocus = trigger;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        continueBtn.focus();
      }

      function closeModal() {
        pendingCoupon = '';
        pendingReferral = '';
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
      }

      document.querySelectorAll('.coupon-launch').forEach(function (link) {
        link.addEventListener('click', function (event) {
          event.preventDefault();
          openModal(link.getAttribute('data-coupon-url') || link.href, link.getAttribute('data-referral-url') || '', link);
        });
      });

      continueBtn.addEventListener('click', function () {
        var url = pendingCoupon;
        closeModal();
        openUrl(url);
      });

      exploreBtn.addEventListener('click', function () {
        var url = pendingReferral;
        closeModal();
        openUrl(url);
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
