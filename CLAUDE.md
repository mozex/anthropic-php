# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Community-maintained PHP API client for the Anthropic API (Claude AI). Namespace: `Anthropic\`. Requires PHP 8.2+.

## Commands

```bash
composer install              # Install dependencies
composer lint                 # Fix code style (Pint)
composer test                 # Run ALL checks (lint, types, type-coverage, unit)
composer test:unit            # Run unit tests only (Pest)
composer test:types           # Run PHPStan (level max)
composer test:type-coverage   # Verify 100% type coverage
composer test:lint            # Check code style without fixing
```

Run a single test file:
```bash
./vendor/bin/pest tests/Resources/Messages.php
```

Run a single test by name:
```bash
./vendor/bin/pest --filter="test name substring"
```

## Architecture

**Layered design with strict dependency boundaries enforced by architecture tests (`tests/Arch.php`):**

- **`Contracts/`** — Interfaces for all major components (Client, Transporter, Resources, Responses)
- **`Resources/`** — API endpoint implementations (Messages, Completions). Use `Transportable` and `Streamable` traits. May only depend on Contracts, ValueObjects, Exceptions, and Responses
- **`Responses/`** — Immutable readonly DTOs with static `from()` factory methods and `toArray()`. Implement `ResponseContract`. May not depend on Resources or Transporters
- **`ValueObjects/`** — Immutable configuration objects (ApiKey, BaseUri, Headers, Payload, etc.)
- **`Transporters/`** — HTTP layer (`HttpTransporter`) using PSR-18 client discovery
- **`Enums/`** — Must be enums only
- **`Exceptions/`** — Must implement `Throwable`
- **`Testing/`** — `ClientFake` and fake responses for consumer testing. Excluded from PHPStan analysis

**Entry points:** `Anthropic::client($apiKey)` for quick setup, `Anthropic::factory()` for configurable builder.

## Code Conventions

- All files use `declare(strict_types=1)`
- Response classes are `final readonly` with private constructors and static `from()` factories
- 100% type coverage is enforced — all parameters, returns, and properties must be typed
- PHPStan runs at max level
- No debugging statements allowed: `dd`, `dump`, `var_dump`, `ray`, `die`, `print_r`
- Architecture tests enforce namespace dependency boundaries — a resource cannot import from transporters, responses cannot import from resources, etc.

## Testing Patterns

Tests use **Pest** syntax: `it('description', function () { ... })` with `expect()` assertions.

Three mock helpers defined in `tests/Pest.php`:
- `mockClient()` — mocks transporter for object responses
- `mockContentClient()` — mocks transporter for content string responses
- `mockStreamClient()` — mocks transporter for streamed responses

Test fixtures live in `tests/Fixtures/` as PHP files returning arrays/strings.

For consumer-facing tests, use `ClientFake` with `CreateResponse::fake()`.
