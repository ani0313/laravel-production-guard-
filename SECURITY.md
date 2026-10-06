# Security Policy

## Safety model

`laravel-production-guard` is **diagnostic and read-only by default**. It is designed to be run safely against a real production application. Specifically, it never:

- Modifies application configuration, `.env`, or any config cache file
- Writes to, migrates, or alters any database schema
- Deletes files, or modifies file permissions (chmod/chown)
- Restarts, reloads, or stops any service (queue workers, Supervisor, PHP-FPM, Nginx, etc.)
- Dispatches real application jobs, or sends real email
- Requires root/sudo privileges
- Executes arbitrary shell commands built from `.env`, database, HTTP request, or CLI-argument input

Any system-level process inspection goes through a single internal `ProcessService` that only ever invokes a fixed, hard-coded whitelist of binaries with escaped arguments. If a capability can't be verified safely without one of the above, the relevant check reports `UNKNOWN` or `NOT_SUPPORTED` rather than guessing or attempting something invasive.

## Secrets

The package only ever checks for the *presence* of sensitive configuration (e.g. `APP_KEY` is set, a mail password is configured) - it never reads out, logs, or includes the actual value of `APP_KEY`, passwords, tokens, API keys, database/Redis credentials, AWS secrets, private keys, OAuth secrets, or SMTP passwords in any report (CLI or JSON).

## Reporting a vulnerability

If you find a security issue in this package, please open a private security advisory on the GitHub repository (or email the maintainer) rather than a public issue, so a fix can be prepared before public disclosure.
