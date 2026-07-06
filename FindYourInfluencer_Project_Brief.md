# FindYourInfluencer — Project Brief (master reference)

Paste this whole file back into a new conversation any time to restore full context.

## The assignment

Take-home for a Full-Stack Developer role at Exhibit Social. Build "FindYourInfluencer" — an internal admin tool that tracks Instagram/YouTube profiles and refreshes their stats via a background job pipeline. This is a hiring test for someone taking over the primary engineering seat on their live production platform, so it's graded far more on system-level robustness than on UI polish.

Stack: Laravel 11/12 + PHP 8.2+, Inertia v2 + React 19 + TypeScript, Tailwind, PostgreSQL, Redis.

Submit to: careers@exhibit.co.in — GitHub repo + seed command + ≤5min Loom video + hours spent + API provider used.

## Deadline (as of this brief)

Assignment doc says 6 calendar days from receipt. HR verbally moved the deadline up to **Monday 12:00 PM IST**. Confirmed current time at last check: Friday, 3 July 2026, ~9:00 PM IST — leaving roughly 63 hours / ~2.5 working days. Worth emailing careers@exhibit.co.in to flag the compressed timeline, since the doc explicitly allows requesting an extension.

## What we're building, in plain terms

A contact book for social profiles that updates itself. Add a handle, a background worker checks its public stats and saves a timestamped snapshot, repeating automatically every hour for everything on the list. Four screens: login, watchlist list (searchable/filterable/paginated), add-handle form, detail page (current stats + history with follower deltas).

## Scoring (this is the actual grading rubric)

| Area | Weight |
|---|---|
| Core CRUD features (§4.A) | 40% |
| Concurrency + locking (§4.B.2) | 15% |
| Rate limiting + circuit breaker (§4.B.3-5) | 15% |
| Database engineering (§4.B.7) | 10% |
| Retry classification + webhook + observability | 10% |
| Code quality, tests, git, README | 10% |

Instant-reject list: Blade UI, synchronous `Http::get()` in a controller, committed `.env`/keys, missing concurrency guard or bare `Cache::lock()`, missing `EXPLAIN ANALYZE`, retrying 401/404, no real tests, SQLite in final submission.

## The 16 build modules (what each is supposed to do)

1. **Scaffold** — Laravel React/TS starter kit, Postgres + Redis wired, auth works.
2. **DB schema** — `profiles` + `profile_snapshots`, FKs with `onDelete`, `timestamptz` everywhere, partial unique index on lowercased username, seeder (1,000+ profiles / 10,000+ snapshots).
3. **Watchlist CRUD** — add-handle form, list page (server-side search/filter/paginate, URL-synced), detail page with follower deltas.
4. **API provider integration** — pick RapidAPI/Apify/YouTube, wrap behind a `ProfileProvider` interface + `FakeProfileProvider` for tests, explicit timeouts.
5. **FetchProfileJob** — the actual HTTP call, status machine `pending → fetching → fetched/failed`, dispatched only from the controller (never synchronous).
6. **Concurrency lock ⭐ (15%)** — guarantee one HTTP call even if two workers grab the same profile at once (advisory lock / `FOR UPDATE SKIP LOCKED` / partial unique index), lock released even on crash, proven with an automated test.
7. **Rate limit / quota** — Redis token bucket checked before every call; empty bucket re-dispatches with backoff instead of failing; YouTube quota keyed by IST date.
8. **Retry classification + circuit breaker** — retriable (5xx/timeout/429) vs fatal (404/401/validation, no retries); Redis-based breaker opens after 10 fails, cools 2 min, then probes.
9. **Scheduler** — every 10 min, enqueue anything stale >1h, overlapping runs are a no-op.
10. **Webhook** — `POST /webhooks/{provider}`, HMAC verified, 24h replay protection via Redis nonce, responds <2s, real work queued.
11. **Tests** — feature, unit (retry logic), job (`Queue::fake`), the concurrency test, 3 webhook cases.
12. **DB engineering proof** — `EXPLAIN ANALYZE` before/after a composite index, transactional snapshot+profile write, indexed time-series query.
13. **No N+1** — debugbar screenshot showing ≤3 queries on the list page regardless of row count.
14. **Observability** — structured JSON log per job run, `/healthz` checking DB + Redis + recent queue activity.
15. **README + Loom + submission** — setup steps, `.env.example`, concurrency reasoning, EXPLAIN diff, N+1 screenshot, circuit-breaker diagram, 2 trade-offs, what was skipped and why; ≤5min video; real commit history.

## Compressed timeline (given the Monday 12pm deadline)

Fri night: scaffold (2h). Sat: DB schema, CRUD pages, provider integration, FetchProfileJob (~10h). Sun: concurrency lock + test, rate limiting, retry/circuit breaker, scheduler, webhook, test suite, DB proof (~13h). Mon AM: observability, README, Loom, submit (~4h). Total ~28h — prioritize modules 6 through 10 (the 60%-weighted system-level work) over CRUD polish if time runs short.

## Design decisions made so far

- Backend architecture diagram: layered (Frontend → HTTP → Queue & Concurrency Safety → Worker → Provider Integration → Data), sky-blue theme, deeper blue marking the safety-critical layer.
- Watchlist page UI mockup: sidebar + stat cards + filterable table, matching Dhruv's existing ThinkOne product's dashboard layout/conventions but with blue as the accent color.

## Files already produced this session

- `FindYourInfluencer_Roadmap.md` — technical module-by-module roadmap.
- `FindYourInfluencer_Roadmap_Simple.md` — plain-language version with the restaurant/kitchen analogy.
- This brief.

## Other standing context (Dhruv / ThinkOne)

Dhruv builds Thinkone, a multi-module SaaS (logistics, sales & marketing, finance, POS, HR, OMS, warehouse management, port management — interdependent but independently runnable modules). Stack: Laravel, Python, React, Redis, MySQL. Dhruv handles all rebuilding/deployment himself — Claude should never do that part. Prefers concise, direct responses with minimal formatting.
