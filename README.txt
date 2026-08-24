UDEMY COUPON MINI-SITE

Upload the contents of this folder to the web root for udemy.dr-chuck.com.
PHP with the SQLite3 extension is required.

Files:
  index.php           Public page: redirects if there are no courses
  crud.php            Password-protected course and coupon CRUD
  text.php            Password-protected website text (welcome paragraph, etc.)
  config.sample.php   Copy to config.php and set password + identity fields
  db.php              SQLite helpers (not a public page)
  utm-live.php        UTM tracker + live results page
  coupons.sqlite      Created automatically on first use
  utm.sqlite          Created automatically on first home-page visit
  styles.css          Layout and styling

Setup:
  1. Copy config.sample.php to config.php
  2. Set $ADMIN_PASSWORD
  3. Set $UDEMY_HOMEPAGE, $DISPLAY_NAME, $SITE_TITLE, $SITE_HOST, $INTRO, and $HERO_IMAGE
  4. Open /crud.php to add courses (each with a referral code) and coupons
  5. Open /text.php to edit public-page wording (overrides config.php)

Schema:
  courses: name, referral URL (parsed into course URL + referral code), description, sort order
  coupons: belong to a course, coupon code, expiration date, optional short description
  site_text: optional overrides for public-page wording (database > config.php > built-in default)

Behavior:
  No courses → visitors are sent to $UDEMY_HOMEPAGE
  Courses with live coupons → coupon ticket opens a confirm dialog
    Use this coupon → couponCode URL
    Explore the course first → referralCode URL (does not use the coupon click)
  Courses with no live coupon → course name opens the referral URL
  Expired coupons stay in the admin table but are hidden from the public page
  Reorder changes course order on the public page

UTM TRACKING
  Home page visits that include standard utm_* query parameters are recorded,
  including visits that immediately redirect to the Udemy homepage.
  Example:
    https://udemy.dr-chuck.com/?utm_source=email&utm_medium=newsletter

  Bare ?utm=qr is treated as utm_source=qr.
  View live counts at /utm-live.php (same password as crud.php unless
  you create a separate utm-password.php).

No libraries, frameworks, or build step are required.
