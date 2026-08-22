UDEMY COUPON MINI-SITE

Upload the contents of this folder to the web root for udemy.dr-chuck.com.
PHP with the SQLite3 extension is required.

Files:
  index.php           Public page: redirects if there are no live coupons
  crud.php            Password-protected coupon CRUD
  config.sample.php   Copy to config.php and set password + Udemy homepage
  db.php              SQLite helpers (not a public page)
  utm-live.php        UTM tracker + live results page
  coupons.sqlite      Created automatically on first use
  utm.sqlite          Created automatically on first home-page visit
  styles.css          Layout and styling
  assets/chuck.jpg    Photo on the public page

Setup:
  1. Copy config.sample.php to config.php
  2. Set $ADMIN_PASSWORD
  3. Set $UDEMY_HOMEPAGE, $DISPLAY_NAME, $SITE_TITLE, $SITE_HOST, $INTRO, and $HERO_IMAGE
  4. Open /crud.php to add coupons

Behavior:
  No unexpired coupons → visitors are sent to $UDEMY_HOMEPAGE
  One or more live coupons → show the homepage link plus the coupon list

Each coupon: course name, link (with coupon code in the URL), short description, expiration date.
Expired coupons stay in the admin table but are hidden from the public page.
The public list follows the order set with the Reorder button in crud.php.

UTM TRACKING
  Home page visits that include standard utm_* query parameters are recorded,
  including visits that immediately redirect to the Udemy homepage.
  Example:
    https://udemy.dr-chuck.com/?utm_source=email&utm_medium=newsletter

  Bare ?utm=qr is treated as utm_source=qr.
  View live counts at /utm-live.php (same password as crud.php unless
  you create a separate utm-password.php).

No libraries, frameworks, or build step are required.
