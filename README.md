# FindYourInfluencer

Influencer watchlist tracker — Laravel API + React/Vite SPA + PostgreSQL + Redis. For stack deviations, what's built, and trade-offs, see `SUBMISSION_NOTES.md`.

## Stack

- Laravel 11 (PHP) — runs natively via `php artisan serve`
- React 18 + Vite, plain JS (not TypeScript) — runs natively via `npm run dev`
- PostgreSQL 16, Redis 7, queue worker, scheduler, pgAdmin — run in Docker
- Auth: Laravel Sanctum, SPA session-cookie mode

## One-time setup

1. Enable Postgres in `php.ini` (find yours with `php --ini`): uncomment `extension=pdo_pgsql` and `extension=pgsql`. No restart needed.
2. Install Composer and Node if you don't already have them.
3. From the project root:

```bash
docker compose up -d
composer install
npm install
php artisan migrate --seed
```

## Running it

Three terminals:

```bash
docker compose up -d          # postgres, redis, queue, scheduler, pgadmin
php artisan serve --port=8010 # keep open
npm run dev                    # keep open
```

- App: http://localhost:8010
- pgAdmin: http://localhost:5050 (`admin@findyourinfluencer.com` / `password`)
- Login: `admin@findyourinfluencer.local` / `password`

## Config gotchas

- `APP_TIMEZONE` must stay `UTC` — it was briefly `Asia/Kolkata`, which made stored timestamps land hours in the future (naive datetimes getting misread against Postgres's UTC session).
- Laravel runs natively (`127.0.0.1` + Docker-published ports in `.env`), but the `queue`/`scheduler` containers run *inside* Docker and need internal hostnames (`postgres`, `redis`) — that override already lives in `docker-compose.yml`, not `.env`. Don't change one without the other.
- YouTube goes through Apify too (`scrapers-hub/youtube-profile-scraper`), not Google's Data API — same `APIFY_API_TOKEN` covers both platforms.

## Concurrency lock

`FetchProfileJob` takes a Postgres advisory lock (`pg_try_advisory_lock`) on the profile id before fetching — if a second worker grabs the same profile mid-fetch, it backs off and retries shortly instead of double-calling the provider. Chosen over `Cache::lock()` because it's tied to the database session: if a worker crashes, Postgres releases the lock automatically — no TTL guesswork, no orphaned lock. Proven in `tests/Feature/ConcurrencyLockTest.php` using a second, independent Postgres connection.

## Running the tests

```bash
createdb -h 127.0.0.1 -p 5442 -U fyi_user findyourinfluencer_test   # one-time
php artisan test
```

41 tests (feature, unit, job, concurrency, webhook), all against real Postgres — deliberately never SQLite, since the advisory lock and a few other things under test can't run on it.
