# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Community-maintained PHP API client for the Anthropic API (Claude AI). Namespace: `Anthropic\`. Requires PHP 8.2+. Modeled on [openai-php/client](https://github.com/openai-php/client), so when in doubt about patterns or conventions, that repo is a useful reference.

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

## Workflow: Implementing New Features

When asked to develop a new feature, work on a roadmap item, or add support for a new API capability, **always research first, implement second**:

1. **Read the official Anthropic API documentation.** Fetch the relevant pages from `platform.claude.com/docs/` using WebFetch. Understand the exact request schema, response schema, streaming event format, and edge cases. Don't guess or rely on training data alone; the docs may have changed.
2. **Examine existing code patterns.** Read the relevant resource, response DTOs, and tests to understand how similar features are already implemented. Match those patterns exactly.
3. **Build test fixtures from documentation examples.** Never fabricate fixture values. Extract the exact JSON request/response examples from the docs and use those values verbatim (strings, IDs, signatures, text). This way, tests passing means the code actually handles real API responses correctly. For values the docs truncate with "...", drop the trailing dots but keep the rest as-is. If the docs only show a partial response (e.g., just the `content` array), compose the outer envelope from existing fixture patterns.
4. **Implement and test.** Write the code, then run `test:unit`, `test:types`, and `test:lint`.
5. **Update the README** with usage examples that match the documented API, and update this CLAUDE.md if the new feature introduces patterns worth preserving.

## Architecture

Layered design with strict dependency boundaries enforced by architecture tests (`tests/Arch.php`):

- **`Contracts/`** -- Interfaces for all major components (Client, Transporter, Resources, Responses)
- **`Resources/`** -- API endpoint implementations (Messages, Completions, Models, Batches). Use `Transportable` and `Streamable` traits. May only depend on Contracts, ValueObjects, Exceptions, and Responses
- **`Responses/`** -- Immutable readonly DTOs with static `from()` factory methods and `toArray()`. Implement `ResponseContract`. May not depend on Resources or Transporters
- **`ValueObjects/`** -- Immutable configuration objects (ApiKey, BaseUri, Headers, Payload, etc.)
- **`Transporters/`** -- HTTP layer (`HttpTransporter`) using PSR-18 client discovery
- **`Enums/`** -- Must be enums only
- **`Exceptions/`** -- Must implement `Throwable`
- **`Testing/`** -- `ClientFake` and fake responses for consumer testing. Excluded from PHPStan analysis

**Entry points:** `Anthropic::client($apiKey)` for quick setup, `Anthropic::factory()` for configurable builder.

**Request flow:** Parameters pass through as-is to the API. `Payload::create()` wraps them, `HttpTransporter` JSON-encodes and sends them. No parameter validation or transformation happens in this client. This means new API parameters (like `thinking.type`, `output_config.effort`) work automatically without code changes on the request side. New features usually only need response DTO updates, tests, and documentation.

## Code Conventions

- All files use `declare(strict_types=1)`
- Response classes are `final` with `readonly` properties, private constructors, and static `from()` factories
- 100% type coverage is enforced; all parameters, returns, and properties must be typed
- PHPStan runs at max level
- **Never remove PHPDoc type annotations.** Only expand or improve them. The `@param` and `@return` shapes on `from()` and `toArray()` methods are critical for type coverage. When adding new fields, add them to the existing shapes as optional fields. These annotations can be very long; that is expected and correct.
- No debugging statements allowed: `dd`, `dump`, `var_dump`, `ray`, `die`, `print_r`
- Architecture tests enforce namespace dependency boundaries; a resource can't import from transporters, responses can't import from resources, etc.

### Property Naming Conventions

These vary by DTO. Check the actual source before writing tests or examples:

- **CreateResponse**: snake_case (`stop_reason`, `stop_sequence`)
- **Usage DTOs**: camelCase (`inputTokens`, `outputTokens`, `cacheCreationInputTokens`)
- **Content DTOs**: snake_case (`type`, `thinking`, `signature`, `data`, `partial_json`)
- **Streamed response**: snake_case (`content_block_start`, `delta`, `message`, `usage`)
- **CountTokensResponse**: camelCase (`inputTokens`)

## Testing Patterns

Tests use **Pest** syntax: `test('description', function () { ... })` with `expect()` assertions.

Three mock helpers defined in `tests/Pest.php`:
- `mockClient()` -- mocks transporter for object responses (verifies HTTP method, path, and request body match)
- `mockContentClient()` -- mocks transporter for content string responses
- `mockStreamClient()` -- mocks transporter for streamed responses

### Fixtures

Test fixture functions live in `tests/Helpers/` as PHP files returning arrays:
- `tests/Helpers/Message.php` -- Messages resource fixtures (`messagesCompletion()`, `messagesCompletionWithThinking()`, etc.)
- `tests/Helpers/Completion.php` -- Completions resource fixtures
- `tests/Helpers/Batches.php` -- Batches resource fixtures
- `tests/Helpers/Models.php` -- Models resource fixtures
- `tests/Helpers/Meta.php` -- Meta/header fixtures

Streaming fixtures are SSE text files in `tests/Helpers/Streams/` (opened with `fopen()` and wrapped in a PSR-7 Stream).

For consumer-facing tests, use `ClientFake` with `CreateResponse::fake()`.
