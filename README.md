<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## Vremeplov development

The project test runtime is PHP 8.2. Run the isolated SQLite test suite with:

```bash
herd php composer.phar test
```

GLS shipment creation requires these environment variables and deliberately
fails before contacting GLS when any required value is missing:

```dotenv
GLS_CLIENT_NUMBER=
GLS_USERNAME=
GLS_PASSWORD=
GLS_WSDL=https://api.mygls.hr/ParcelService.svc?singleWsdl
GLS_CONNECTION_TIMEOUT=20
GLS_PICKUP_CONTACT_NAME=
GLS_PICKUP_CONTACT_PHONE=
GLS_PICKUP_CONTACT_EMAIL=
GLS_PICKUP_NAME="Antikvarijat Vremeplov"
GLS_PICKUP_STREET=
GLS_PICKUP_HOUSE_NUMBER=
GLS_PICKUP_CITY=
GLS_PICKUP_ZIP_CODE=
GLS_PICKUP_COUNTRY_CODE=HR
GLS_TRACKING_URL=https://gls-group.com/GROUP/en/parcel-tracking?match={tracking_code}
```

Abandoned-checkout emails are installed disabled. After confirming the lawful
basis and sender configuration, enable only new checkouts with:

```dotenv
ABANDONED_CART_EMAILS_ENABLED=true
ABANDONED_CART_STARTS_AT="2026-09-04 00:00:00"
```

Review invitations are also installed disabled. After migrations, inspect the
eligible orders without sending anything, then enable the scheduler:

```bash
php artisan reviews:send-requests --dry-run
```

```dotenv
REVIEW_REQUEST_EMAILS_ENABLED=true
REVIEW_REQUEST_DELAY_DAYS=30
REVIEW_REQUEST_MAX_ATTEMPTS=3
REVIEW_REQUEST_LINK_DAYS=180
REVIEW_REQUEST_ELIGIBLE_STATUSES=4,9,10
```

After deploying tracking migrations, use `php artisan migrate --force`. The
legacy `database/008_add_boxnow_shipping_tracking.sql` remains available only
for installations that cannot run Laravel migrations.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
