# Tests

PHPUnit test suite for the business logic added around reservations and payments.

- `tests/Unit/` — fast unit tests for the pure domain services (pricing,
  availability rules, locker state), no database or HTTP.
- `tests/Functional/` — HTTP functional tests using the API Platform test
  client (`AbstractApiTestCase`). They hit the real endpoints in-process (no
  running web server), seed the data they need and reset the test database
  before each test.

## Setup (once)

The functional tests use a dedicated `app_test` database. The `app` MariaDB user
must be allowed to use it:

```bash
docker compose exec database mariadb -uroot -proot_password \
  -e "CREATE DATABASE IF NOT EXISTS app_test; GRANT ALL ON app_test.* TO 'app'@'%'; FLUSH PRIVILEGES;"

make test-init   # creates the test schema (re-run after entity changes)
```

JWT keys must exist (shared with the dev env):

```bash
docker compose exec apache php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

## Run

```bash
make test
# or a single suite / file:
docker compose exec apache php vendor/bin/phpunit tests/Unit
docker compose exec apache php vendor/bin/phpunit tests/Functional/ReservationGuardTest.php
```

## What is covered

| Area | File |
|------|------|
| Anti double-booking, min/max duration, unbookable lockers | `Functional/ReservationGuardTest.php` |
| Cancellation lifecycle + illegal transitions | `Functional/ReservationLifecycleTest.php` |
| Auto-expiry command | `Functional/ReservationExpirationTest.php` |
| Stripe webhook (confirm/fail, locker sync, idempotency, signature) | `Functional/StripeWebhookTest.php` |
| Payment idempotency | `Functional/PaymentIdempotencyTest.php` |
| Password change | `Functional/CustomerPasswordTest.php` |
| Pricing / overtime / availability / locker state | `Unit/Service/*Test.php` |
