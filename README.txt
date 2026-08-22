UDEMY COUPON MINI-SITE

Upload the contents of this folder to the web root for udemy.dr-chuck.com.
PHP with the SQLite3 extension is required.

Files:
  index.php           Public page: redirects if there are no live coupons
  crud.php            Password-protected coupon CRUD
  config.sample.php   Copy to config.php and set password + Udemy homepage
  db.php              SQLite helpers (not a public page)
  coupons.sqlite      Created automatically on first use
  styles.css          Layout and styling
  assets/chuck.jpg    Photo on the public page

Setup:
  1. Copy config.sample.php to config.php
  2. Set $ADMIN_PASSWORD
  3. Set $UDEMY_HOMEPAGE to your Udemy instructor page
  4. Open /crud.php to add coupons

Behavior:
  No unexpired coupons → visitors are sent to $UDEMY_HOMEPAGE
  One or more live coupons → show the homepage link plus the coupon list

Each coupon: course name, link (with coupon code in the URL), short description, expiration date.
Expired coupons stay in the admin table but are hidden from the public page.
The public list follows the order set with the Reorder button in crud.php.

No libraries, frameworks, or build step are required.
