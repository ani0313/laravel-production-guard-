# Laravel Production Guard

A safe, **read-only**, diagnostic production-readiness checker for Laravel applications. It answers one question:

> Is this Laravel application, and the server it runs on, actually ready and healthy for production?

It is **diagnostic and read-only by default**. It never modifies your application, its configuration, its database, or any running service - it only observes and reports. Every recommendation it gives is something *you* run; the package never runs it for you. See [Security](#security) below for the full safety model.

## Status

Feature-complete for the 1.x release line. All 24 checkers are implemented and tested (145 tests): Environment, Application, PHP, Database, Cache, Storage, Queue, Queue Worker, Redis, Beanstalkd, Horizon, Supervisor, Scheduler, Server, OPcache, PHP-FPM, Nginx, Security, Composer, Migrations, Logging, Mail, Session, and Filesystem. CI runs the suite across PHP 7.1-7.4 (`.github/workflows/tests.yml`). See `CHANGELOG.md` for what landed in each phase.

## Features

- A single `php artisan production:check` command with CLI and JSON output, category filtering, and CI-friendly opt-in exit codes.
- A 0-100 health score, weighted by category, that never falsely claims `passed` for something it couldn't verify.
- An extensible checker system - register your own company-specific checks alongside the built-in ones.
- Every system/network call is bounded by a configurable timeout, and every check is wrapped so one failing checker never aborts the run.
- Nothing destructive, ever: no schema changes, no service restarts, no job dispatches, no real emails, no file deletion, no root/sudo.

## Installation

### Requirements (1.x release line)

- PHP `^7.1`
- Laravel 5.4-5.8 (`illuminate/support`, `illuminate/console`, `illuminate/contracts` `^5.4`)

This is intentionally the only range this release claims. Later Laravel versions get their own release line under the same package name and the same `production:check` command API (see [Laravel/PHP compatibility](#laravelphp-compatibility) below) - compatibility is never claimed before it's actually tested.

### Via Packagist (once published)

```bash
composer require anivesh/laravel-production-guard
```

### Local path-repository (current development setup)

This package is currently developed inside a host application as a local path repository, not yet published to Packagist. In the host application's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/anivesh/laravel-production-guard",
        "options": { "symlink": true }
    }
],
"require": {
    "anivesh/laravel-production-guard": "dev-master"
}
```

Then `composer update anivesh/laravel-production-guard`.

### Registering the service provider

Laravel 5.5+ picks the provider up automatically via this package's `composer.json` `extra.laravel` block. On Laravel 5.4 there is no package auto-discovery, so register it manually in `config/app.php`:

```php
Anivesh\LaravelProductionGuard\LaravelProductionGuardServiceProvider::class,
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=production-guard-config
```

## Basic usage

```bash
php artisan production:check
```

```
Laravel Production Guard

Environment
✓ APP_ENV = production
✓ APP_DEBUG = false
✓ APP_KEY configured
✓ APP_URL configured (https://app.example.com)

Database
✓ Database connection successful (mysql).
✓ Query latency: 4ms
✓ Database version: 8.0.35

Queue
⚠ Queue driver is synchronous. Jobs execute during the request lifecycle.
    -> Use a real queue driver (database, redis, beanstalkd, sqs) in production.

Queue Worker
? Queue worker status could not be verified.

Health Score: 91/100
Status: WARNING
```

### CLI examples

```bash
php artisan production:check                          # CLI report, exit 0 by default
php artisan production:check --json                    # JSON report
php artisan production:check --only=database            # a single category
php artisan production:check --only=database --only=queue   # several categories
php artisan production:check --only=all                # explicit "everything" (same as omitting --only)
php artisan production:check -v                        # -v/-q are the normal Artisan/Symfony Console flags
php artisan production:check --fail-on-warning          # opt in to a non-zero exit on warnings
php artisan production:check --fail-on-critical         # opt in to a non-zero exit on critical issues
```

### Exit codes / CI-CD usage

By default `production:check` always exits `0` (unless the command itself errors) - it is safe to drop into any script or deploy pipeline without accidentally breaking it just by running it. Gate CI/CD on the result by opting in explicitly:

| Code | Meaning |
|---|---|
| 0 | Default, or `--fail-on-warning`/`--fail-on-critical` was passed but nothing matched |
| 1 | `--fail-on-warning` was passed and a `warning` or `critical` check is present |
| 2 | `--fail-on-critical` was passed and a `critical` check is present (takes priority over code 1) |
| 3 | The command itself errored (not a check failing - the run itself) |

A typical deploy pipeline step:

```yaml
- name: Production readiness check
  run: php artisan production:check --fail-on-critical
```

### JSON output

```bash
php artisan production:check --json
```

```json
{
    "package": "laravel-production-guard",
    "version": "1.0.0-dev",
    "laravel": "5.4.36",
    "php": "7.1.33",
    "environment": "production",
    "score": 91,
    "status": "warning",
    "checks": [
        {
            "category": "database",
            "name": "Database",
            "status": "passed",
            "score": 30,
            "max_score": 30,
            "message": "All 3 check(s) passed.",
            "details": [
                { "status": "passed", "message": "Database connection successful (mysql)." },
                { "status": "passed", "message": "Query latency: 4ms" },
                { "status": "passed", "message": "Database version: 8.0.35" }
            ],
            "recommendations": []
        }
    ]
}
```

No report (CLI or JSON) ever includes `APP_KEY`, passwords, tokens, or other credentials - checks only verify *presence*, never value. See [Security](#security).

## Status model

Every check reports one of:

- **`passed`** - verified and healthy.
- **`warning`** - a potential issue; the app likely still functions.
- **`critical`** - a real production risk.
- **`unknown`** - could not be safely determined. Never reported as a false `passed`.
- **`not_supported`** - not checkable on this Laravel/PHP/OS combination (e.g. CPU usage on a non-Linux host).
- **`not_applicable`** - an optional feature that isn't installed/active (e.g. Horizon when it's not required). Never reduces the score.

## Health scoring

0-100, computed per category and combined with configurable weights (`config('production-guard.weights')`). A category only contributes if it produced at least one `passed`/`warning`/`critical` result in that run - a category that's entirely `unknown`/`not_supported`/`not_applicable` is excluded from the average rather than penalized, and likewise within a single check's own internal facts. The overall status is always derived from the *worst* result present, never from the score alone - a high score with one `critical` fact anywhere still reports `critical` overall, and a run where nothing could be verified reports `unknown` rather than a false `passed`.

## What each category checks

| Category | What it verifies |
|---|---|
| Environment | APP_ENV, APP_DEBUG, APP_KEY presence, APP_URL sanity |
| Application | Laravel version, maintenance mode, config/route/view/event cache, core directories |
| PHP | Version, required vs. commonly-needed extensions, ini directives (informational) |
| Database | Connection, latency, version (read-only; never runs migrations) |
| Cache | Driver, a randomized-key read/write round trip |
| Queue | Driver, driver-specific connectivity (database/redis/beanstalkd/sqs), failed-jobs count |
| Queue Worker | Distinguishes "queue connection healthy" from "a worker process is actually running" (`ps aux`, then Supervisor as a fallback) - reports `unknown` rather than ever guessing |
| Redis | Ping, randomized-key read/write, best-effort memory usage |
| Beanstalkd | TCP-level reachability only (no extra dependency needed) |
| Horizon | Installed/paused/master-supervisor state; `not_applicable` when not installed |
| Supervisor | `supervisorctl status`; `unknown` when unavailable |
| Scheduler | Counts registered tasks (never executes them); execution status is always `unknown` - cron's actual firing can't be safely verified generically |
| Server | CPU/cores/load/memory/swap (Linux-only via `/proc`; load average works everywhere) |
| OPcache | Distinguishes "disabled" from "enabled but unobservable from this CLI process" |
| PHP-FPM / Nginx | Process detection (`ps aux`) plus a best-effort version string |
| Security | HTTPS enforcement, secure session cookie, `.env` permissions, debug packages (Telescope/Debugbar) in production, opt-in security-header probe |
| Composer | `vendor/`/`composer.lock` presence, abandoned packages, informational PHP-constraint-vs-running-version |
| Migrations | Pending migrations (read-only; never runs them) |
| Logging | Default channel, `storage/logs` writability, opt-in-only test log write |
| Mail | Driver/host/port/encryption/username presence - never the password |
| Session | Driver, `array` driver warning, storage/table reachability |
| Filesystem | Each configured disk - local disks get a writability check, cloud disks get a configuration-presence check only |

## Configuration

See `config/production-guard.php` for the full, commented set. Highlights:

```php
'checks' => [
    'database' => true,
    'network' => false, // disabled: reserved for a future, opt-in network checker
    // ... one toggle per category
],

'weights' => [
    'database' => 15,
    'security' => 15,
    // ...
],

'thresholds' => [
    'cpu_warning' => 80, 'cpu_critical' => 95,
    'disk_warning' => 80, 'disk_critical' => 90,
    // ...
],

'timeouts' => [
    'database' => 3, 'redis' => 3, 'beanstalkd' => 3, 'http' => 5,
],

'external' => [
    'enabled' => false, // the package never contacts a URL unless you list one here
    'urls' => [],
],

'logging' => [
    'test_write_enabled' => false, // the package never writes a log line unless you opt in
],

'notifications' => [
    'enabled' => false, // reserved for a future monitoring mode
],
```

## Custom checks

```php
use Anivesh\LaravelProductionGuard\Facades\ProductionGuard;

ProductionGuard::registerCheck(MyCustomCheck::class);
```

Call this from any service provider's `boot()` method - it can run any time before `production:check` actually executes. Implement `Anivesh\LaravelProductionGuard\Contracts\Check`:

```php
use Anivesh\LaravelProductionGuard\Contracts\Check;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

class MyCustomCheck implements Check
{
    public function getName()
    {
        return 'My Custom Check';
    }

    public function getCategory()
    {
        return 'security'; // a Anivesh\LaravelProductionGuard\Enums\CheckCategory constant, or your own string
    }

    public function check()
    {
        return new CheckResult($this->getName(), $this->getCategory(), CheckStatus::PASS, 10, 10, 'Everything looks good.');
    }
}
```

Checks are resolved through the Laravel container, so constructor dependency injection works. For a check that observes several internal facts (the way the built-in checkers do), extend `Anivesh\LaravelProductionGuard\Checkers\AbstractCheck` and call `$this->aggregate($facts)` instead of building a `CheckResult` by hand - see any built-in checker for the pattern.

`--only=<category>` works with custom category strings too, including ones not in the built-in `CheckCategory` list.

## Notifications

Reserved for a future `production:monitor` mode (email/Slack/Telegram/Discord/webhook). The `Contracts\Notifier` interface exists for forward compatibility, but nothing is wired up yet, and `config('production-guard.notifications.enabled')` defaults to `false` - this package never sends anything anywhere on its own today.

## Laravel/PHP compatibility

| Package line | Laravel | PHP |
|---|---|---|
| 1.x (this line) | 5.4-5.8 | ^7.1 |
| 2.x (planned) | 6-8 | - |
| 3.x (planned) | 9-10 | - |
| 4.x (planned) | 11-13 | - |

Same package name, same `production:check` command API across every line. Compatibility is never claimed for a line until it's actually tested against that Laravel/PHP combination - see the CI workflow (`.github/workflows/tests.yml`) for exactly what's verified today.

## Troubleshooting

- **"Target [X] is not instantiable" when resolving a checker** - if you're writing a custom checker, double check which Laravel services are bound to their interface vs. a string container key. A few core services (the DB connection resolver, the migration repository, the Redis manager) are bound only to string keys (`'db'`, `'migration.repository'`, `'redis'`) - resolve those via `Illuminate\Contracts\Container\Container::make('db')` etc. inside your checker rather than type-hinting the interface directly. The built-in `DatabaseCheck`/`SessionCheck`/`RedisCheck`/`QueueCheck` all do this.
- **A checker always reports `unknown`** - that's very likely working as intended. `unknown` means this package could not safely verify something generically (e.g. no `/proc` on this OS, `supervisorctl` not installed, cron execution history) - it's the honest answer, not a bug, unless you have a reason to believe the underlying thing actually IS verifiable here.
- **The score didn't change even though a check is `critical`** - weights for several categories (Redis, Beanstalkd, Horizon, Supervisor, OPcache, Composer, Migrations, Logging, Mail, Session, Filesystem, PHP-FPM, Nginx) default to `0` in `config('production-guard.weights')`, since they mostly back an already-scored category (e.g. Redis backs Queue/Cache). A `critical` result in a zero-weight category still flips the overall *status* to `critical` (status is always derived from the worst result, not the score) - only the numeric score is unaffected. Adjust the weight if you want that category to also move the number.
- **`production:check` hangs** - every external/system call is bounded by `config('production-guard.timeouts')`. If it's still slow, check which category's `--only` isolates the delay and verify its timeout is actually being read (e.g. a custom check you registered that doesn't honor the config).

## Security

- Never modifies application config, `.env`, or any config cache file.
- Never writes to, migrates, or alters any database schema.
- Never deletes files, or changes file permissions (chmod/chown).
- Never restarts, reloads, or stops any service (queue workers, Supervisor, PHP-FPM, Nginx, etc.).
- Never dispatches real application jobs, and never sends real email.
- Never requires root/sudo.
- Never executes a shell command built from `.env`, database, HTTP-request, or CLI-argument input - all process inspection goes through one internal `ProcessService` with a fixed, hard-coded binary whitelist and escaped arguments.
- Never reports the *value* of `APP_KEY`, a password, a token, an API key, or any other credential - only its presence.

See `SECURITY.md` for the full policy and how to report a vulnerability.

## Limitations

- `QueueWorkerCheck`/`SupervisorCheck`/`PhpFpmCheck`/`NginxCheck` detect a running process via `ps aux` and `supervisorctl status` - in a container/orchestrator setup where those tools can't see the actual process (e.g. it runs in a different PID namespace), they honestly report `unknown` rather than a false negative.
- `ServerCheck`'s CPU/core/memory/swap metrics are Linux-only (`/proc`) - they report `unknown` on any other OS. Load average works everywhere via `sys_getloadavg()`.
- `SchedulerCheck` can confirm tasks are *registered* but can never confirm cron is *actually running* them - that fact always reports `unknown`, by design; there's no safe, generic way to read the deployment's actual cron user's crontab from inside the app.
- `ComposerCheck`'s PHP-version-constraint fact is informational only - it does not attempt to evaluate whether the running PHP version satisfies the composer.json constraint, since correctly hand-rolling full composer version-range syntax risked a wrong computed verdict, which would be worse than just surfacing the two values.
- `ApplicationCheck`'s view-cache fact is a best-effort proxy (whether `storage/framework/views` contains any compiled template), not a guarantee every view is precompiled.
- This is the 1.x line only (Laravel 5.4-5.8, PHP `^7.1`). Later Laravel/PHP support is a separate, not-yet-started release line - see [Laravel/PHP compatibility](#laravelphp-compatibility).

## Contributing

This package is being developed inside a private application and is not yet open-sourced. Contribution guidelines will be added once it's extracted into its own repository.
