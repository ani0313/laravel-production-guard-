# Changelog

All notable changes to this package are documented here.

## [Unreleased]

### Added - Phase 1 (package skeleton)

- Package skeleton: service provider, `config/production-guard.php`, contracts (`Check`, `Reporter`, `Notifier`, `SystemMetrics`), DTOs (`CheckResult`, `ProductionReport`), const-based status/category sets (`CheckStatus`, `CheckCategory`), `CheckRegistry`, `ScoreCalculator`, `ProcessService`, `ProductionGuard` orchestrator service + `ProductionGuard` facade.
- `php artisan production:check` command with `--json`, `--only=`, `--fail-on-warning`, `--fail-on-critical` (global `-v`/`-q` are inherited from Artisan, not redeclared).
- `CliReporter` and `JsonReporter`.

### Added - Phase 2 (first six checkers)

- `Checkers\AbstractCheck::aggregate()`: combines several internal "facts" (e.g. APP_ENV, APP_DEBUG, APP_KEY) into one `CheckResult`, carrying each fact in `details` so `CliReporter` renders one line per fact, matching the documented multi-line-per-category CLI format.
- `EnvironmentCheck` (APP_ENV/APP_DEBUG/APP_KEY/APP_URL), `ApplicationCheck` (Laravel version, maintenance mode, config/route/view/event cache, core directories), `PhpCheck` (PHP version, required vs. common extensions, ini directives), `DatabaseCheck` (connection, latency, version, pending migrations - read-only), `CacheCheck` (driver, safe read/write round-trip with a randomized test key), `StorageCheck` (writability, temp-file round trip, disk usage against configurable thresholds).
- All six are registered by default, gated individually by `config('production-guard.checks.*')`.

### Added - Phase 3 (queue infrastructure)

- `QueueCheck` (driver, driver-specific connectivity for database/redis/beanstalkd/sqs, failed-jobs count), `QueueWorkerCheck` (distinguishes "queue connection healthy" from "a worker process is actually running" via `ps aux` then Supervisor as a fallback - reports `UNKNOWN` rather than ever guessing), `RedisCheck` (ping, randomized-key read/write, best-effort memory usage), `BeanstalkdCheck` (TCP-level reachability only, no `pda/pheanstalk` dependency), `HorizonCheck` (installed/paused/master-supervisor state, `NOT_APPLICABLE` when not installed), `SupervisorCheck` (via `supervisorctl status`, `UNKNOWN` when unavailable).
- Fixed a real bug in `ProcessService` found while testing these: `proc_get_status()` only reports the correct exit code on the first call that observes termination - a later `proc_close()` return (or a later `proc_get_status()` call) gives `-1`. Exit code is now captured at the moment termination is first observed.
- `ProcessService` is no longer `final` (checkers that shell out need it mockable in tests).

### Added - Phase 4 (scheduler and server-level checks)

- `Services\SystemMetricsService` (implements `Contracts\SystemMetrics`): Linux-first (`/proc/stat`, `/proc/cpuinfo`, `/proc/meminfo`) CPU/cores/memory/swap, `sys_getloadavg()` for load (works cross-platform), `disk_total_space()`/`disk_free_space()` wrappers. Every method returns `null` rather than guessing on an unsupported OS - parsing is factored into pure static methods so it's fully unit-tested regardless of host OS.
- `SchedulerCheck`: counts registered scheduled tasks (via the real `Schedule::events()`, never executed) and always reports execution status as `unknown` - whether cron is actually firing can't be safely determined generically.
- `ServerCheck`: CPU, cores, load average, memory, swap against `config('production-guard.thresholds')` (disk stays in `StorageCheck` from Phase 2 - not duplicated here). Swap at 0 total reports "disabled" rather than a meaningless percentage.
- `OpcacheCheck`: distinguishes `opcache.enable=0` (genuinely disabled, `warning`) from `opcache.enable=1` but `opcache_get_status()` unreadable (common when run via CLI, which has its own `opcache.enable_cli` - reported `unknown`, not a false `warning`).
- `PhpFpmCheck`/`NginxCheck`: process detection via `ps aux` (this command always runs under the CLI SAPI, so `PHP_SAPI` can never reveal whether a separate FPM/Nginx process is serving real traffic) plus a best-effort version string from `php-fpm -v`/`nginx -v`.

### Added - Phase 5 (security, dependencies, and remaining config categories)

- `MigrationCheck`: pending-migrations detection extracted out of `DatabaseCheck` into its own checker/category (`DatabaseCheck` now covers connection/latency/version only), so the same fact isn't duplicated under two headings.
- `SecurityCheck`: HTTPS enforcement, secure session cookie, `.env` world-readability, debug/dev packages (Telescope/Debugbar) left active in production, and - only when `production-guard.external` is explicitly configured - a security-headers probe against one configured URL. (APP_DEBUG/APP_ENV/APP_KEY/APP_URL presence stay EnvironmentCheck's job, not duplicated here.)
- `ComposerCheck`: `vendor/` presence, `composer.lock` presence, abandoned-package detection by scanning the lock file's `abandoned` keys, and an informational (not judged) PHP-version-constraint-vs-running-version fact - evaluating a full composer constraint correctly is genuinely hard to hand-roll, and a wrong computed verdict would be worse than just surfacing the two values.
- `LoggingCheck`: default channel, `storage/logs` writability, and an opt-in-only test log write (`production-guard.logging.test_write_enabled`, default `false`).
- `MailCheck`: driver/host/port/encryption/username presence (reads both the legacy `mail.driver` and Laravel 9+ `mail.default`/`mail.mailers.*` shapes) - never touches or logs `mail.password`.
- `SessionCheck`: driver, `array` driver warning, `storage/framework/sessions` writability for the `file` driver, sessions-table reachability for the `database` driver.
- `FilesystemCheck`: iterates every configured disk - local disks get a writability check, cloud disks (currently `s3`) get a configuration-presence check only (no connection attempted).

### Added - Phase 6 (polish)

- `.github/workflows/tests.yml`: CI matrix across PHP 7.1-7.4 (the 1.x line's actual declared range) via `shivammathur/setup-php`, running `composer validate` + the full test suite.
- Full README rewrite covering every documented section: features, installation (Packagist + local path-repo), requirements, Laravel/PHP compatibility table, configuration, CLI examples, JSON output, health scoring, a per-category "what each checker verifies" table, custom checks, notifications (reserved), troubleshooting, security, limitations, contributing.
- More `ScoreCalculator` edge-case tests: `NOT_SUPPORTED` behaving identically to `UNKNOWN`, multiple results within one category averaging correctly before weighting, and the weighted average across differently-weighted categories.

### Fixed

- **`CheckRegistry::resolve()` could crash the entire `production:check` run.** If a registered check's constructor dependency couldn't be resolved by the container (e.g. a misconfigured custom check), the resulting exception was never caught - it would abort the whole command (exit code 3) instead of just marking that one checker `unknown`, breaking the package's own core "one failing checker never aborts the run" guarantee. Fixed with a new `Services\UnresolvedCheck` that stands in for a failed resolution and reports it as a normal `unknown` result - it's also never hidden by a `--only` category filter, since silently dropping a broken custom check behind a filter would be exactly the kind of silent failure this package exists to prevent.
