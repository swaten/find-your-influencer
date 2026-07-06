# Submission notes — FindYourInfluencer

A short cover note on how this was built, where it deviates from the original brief, and why. Setup/installation steps are in `README.md`, not repeated here.

## Stack deviation: Inertia → decoupled API + SPA

The brief asked for Inertia v2 + React 19 + TypeScript + Tailwind. I hadn't worked with Inertia before, so rather than learn it and risk shipping something half-working, I built this as a fully decoupled Laravel REST API (`routes/api.php`, JSON only) with a separate React single-page app (Vite-bundled, React Router owning every client-side route). `resources/views/app.blade.php` is the only Blade file in the whole project — it just mounts the React root; every screen is rendered by React, not Blade, so this doesn't fall into the brief's "Blade UI" instant-reject.

Two more honest deviations from the stated stack: the frontend is plain JavaScript (`.jsx`), not TypeScript, and styling is a custom CSS design system rather than Tailwind. React is 18.3, not 19. If any of these are hard requirements rather than preferences, let me know and I can talk through what converting would take.

## Add-handle flow: fast UI, provider call happens off the request

Adding a handle doesn't wait on Instagram/YouTube at all. The record is created and shown in the table immediately (status `pending`), and the actual Apify call happens afterward in a queued background job (`FetchProfileJob`) — so the add action is instant regardless of how slow or flaky the provider is. Once that job finishes, the row updates itself with real follower/status data without a page reload.

## Refresh Stats

Each row (and the detail page) has a manual "Refresh stats" action that re-triggers the same background fetch on demand. This exists specifically so the app doesn't need to poll every profile on a fixed schedule to stay useful — you refresh what you're actually looking at, when you want current numbers, instead of the whole watchlist hitting the API on a timer regardless of whether anyone's watching.

## Error handling when the API is unavailable/exhausted

If a fetch fails for any reason (provider down, rate-limited, invalid handle, credentials issue), the profile's status flips to `failed` and the specific error is shown right on that profile's detail page, along with how many times it's failed in a row. It doesn't fail silently, and it doesn't retry indefinitely — different failure types are handled differently under the hood (some are worth retrying automatically, some aren't), which you can see in the code.

## Fetch history per profile

Every successful fetch is stored as its own timestamped snapshot, not just overwritten in place. The detail page lists that full history for a profile — when each fetch happened and what the follower count was at that point — so you can see how many times a profile has actually been checked and how its numbers have moved over time.

## Automated tests

Backend logic is covered by an automated PHPUnit suite (feature and unit tests) rather than only manual click-testing — 41 tests, 86 assertions, all passing. Test results screenshot attached.

## Everything else

Rate limiting, retry/circuit-breaker behavior, the concurrency safeguard for the background jobs, webhook handling, and the rest of the backend requirements are also implemented — I'd rather you check the code directly for those than take my summary of them here at face value.

## API provider used

Apify — `apify/instagram-profile-scraper` for Instagram, `scrapers-hub/youtube-profile-scraper` for YouTube. Both are real, live integrations, not mocked data.

## Hours spent

_[fill in — I don't have visibility into your actual time spent across the whole build]_
