# API integration tests (curl-style)

End-to-end smoke tests that hit the running API over HTTP and assert business
rules. They use only the Python standard library — no extra dependencies.

## Prerequisites

The stack must be running with fixtures loaded:

```bash
make up
docker compose exec apache php bin/console doctrine:fixtures:load --no-interaction --no-debug
```

By default the tests target `http://localhost:15436` (override with
`LOCKEDIN_BASE`). Each test reloads the fixtures itself to start from a clean,
deterministic state, so locker/reservation IDs are discovered dynamically.

## Run

```bash
python3 tests/Api/test_reservation_guard.py        # anti double-booking, min/max duration, unbookable lockers
python3 tests/Api/test_reservation_lifecycle.py    # cancellation, refund flag, locker freeing, illegal transitions
python3 tests/Api/test_expiration.py               # app:reservations:expire frees stale holds
python3 tests/Api/test_webhook.py                  # Stripe webhook: confirm/fail, locker sync, idempotency, signature
python3 tests/Api/test_payment_idempotency.py      # duplicate /payments/intents reuses the hold
python3 tests/Api/test_password_change.py          # POST /api/customers/me/password
```

Exit code is non-zero if any assertion fails.

## Unit tests (PHPUnit, no stack required)

Pure domain-logic tests run inside the container without a database:

```bash
docker compose exec apache php vendor/bin/phpunit tests/Unit
```
