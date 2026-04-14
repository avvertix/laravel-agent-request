# CLAUDE.md

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/pest

# Run a single test file
vendor/bin/pest tests/ExampleTest.php

# Run static analysis
vendor/bin/phpstan

# Fix code style
vendor/bin/pint
```

Code style is also auto-fixed by CI (Laravel Pint) on push via the `fix-php-code-style-issues` workflow.

## Architecture

This is a Laravel package built on [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools). The package is in early development (stub stage).

**Root namespace:** `Avvertix\AgentRequest\LaravelAgentRequest`

**Package registration** is handled entirely in `src/LaravelAgentRequestServiceProvider.php` via `configurePackage()`, which wires up:
- Config file: `config/agent-request.php` → published as `laravel-agent-request`
- Artisan command: `src/Commands/LaravelAgentRequestCommand.php`
- Request macros

**Testing** uses Pest with Orchestra Testbench. `tests/TestCase.php` bootstraps the service provider. Migrations in `getEnvironmentSetUp()` are commented out by default — uncomment and adapt when tests need DB access. The arch test in `tests/ArchTest.php` enforces no `dd`, `dump`, or `ray` in source.

**Compatibility matrix:** PHP 8.3/8.4/8.5, Laravel 11.*/12.*/13.*, tested on both Ubuntu and Windows.

**PHPStan** runs at level 5 over `src/`, `config/`, and `database/`.
