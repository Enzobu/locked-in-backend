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
python3 tests/Api/test_reservation_guard.py   # anti double-booking, min/max duration, unbookable lockers
```

Exit code is non-zero if any assertion fails.
