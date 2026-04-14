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
6. **Mirror the change in the Laravel wrapper docs.** See the "Keeping the Laravel wrapper docs in sync" section below. Any feature touching user-facing response shapes, request parameters, or control-flow stop reasons must be reviewed against the Laravel package docs.

## Keeping the Laravel wrapper docs in sync

The Laravel wrapper package (`mozex/anthropic-laravel`) carries its own documentation that intentionally does NOT duplicate this package's docs page-for-page. Instead the wrapper docs act as a **shortened, Laravel-flavored layer** with a footer link back to the equivalent page on `mozex.dev/docs/anthropic-php/v1/...` for full detail.

After every documentation change in this package, open the matching Laravel wrapper page and decide whether to mirror, adapt, or defer. Do not skip this step.

### Page mapping

| PHP doc | Laravel doc |
|---------|-------------|
| `docs/usage/messages.md` | `docs/usage/messages.md` |
| `docs/usage/tool-use.md` | `docs/usage/tool-use.md` |
| `docs/usage/server-tools.md` | `docs/usage/server-tools.md` |
| `docs/usage/models.md` | `docs/usage/models.md` |
| `docs/usage/streaming.md` | `docs/usage/streaming.md` |
| `docs/usage/thinking.md` | `docs/usage/thinking.md` |
| `docs/usage/citations.md` | `docs/usage/citations.md` |
| `docs/usage/batches.md` | `docs/usage/batches.md` |
| `docs/usage/token-counting.md` | `docs/usage/token-counting.md` |
| `docs/usage/completions.md` | `docs/usage/completions.md` |
| `docs/reference/meta-information.md` | `docs/reference/meta-information.md` |
| `docs/reference/error-handling.md` | `docs/reference/error-handling.md` |
| `docs/reference/testing.md` | `docs/reference/testing.md` |
| `docs/reference/configuration.md` | `docs/reference/configuration.md` |

### What belongs in the Laravel docs vs what to defer

**Add (or update) on the Laravel side when:**
- The feature has a natural Laravel idiom — `Log`, `Cache`, `Queue`/`ShouldQueue`, `Storage`, Eloquent, `auth()`, `config()`, controllers, form requests, middleware.
- An existing Laravel example would be subtly wrong without the new field. The `tool_use` dispatcher pattern needing a `$block->caller?->type === 'direct'` filter is the canonical example — miss that and users ship a duplicate-execution bug.
- The feature changes control flow users must handle — new `stop_reason` values, refusal responses, pause-and-resume, rate-limit headers they should throttle on. These usually deserve a code snippet, not just a pointer.
- The feature is something a Laravel dev will reach for in normal app flow (persisting `container_upload` file IDs on an Eloquent model, caching the model list, etc.).

**Defer to the PHP docs (just ensure the footer link exists) when:**
- The change is a pure schema detail — optional fields, every nullable, TS SDK union members, full enum lists.
- The PHP example translates 1:1 with no Laravel twist. Repeating it adds maintenance burden without adding value.
- It's reference material — full header lists, complete `toArray()` shapes, every block type variant, tool version history.
- It's a rare edge case. The Laravel page aims for the 80% path; edge cases belong in the PHP docs.

### Style conventions for the Laravel docs

- **Short.** A Laravel page typically runs half the length of its PHP counterpart.
- **Facade-first.** Every example starts with `use Anthropic\Laravel\Facades\Anthropic;` and calls `Anthropic::messages()->create([...])` rather than `$client->messages()->create(...)`.
- **Laravel primitives in examples.** Reach for `Log::warning`, `Cache::remember`, `Storage::disk`, `ShouldQueue` jobs, Eloquent relationships, `auth()->id()`, `now()->addHour()`. A plain PHP code sample is a signal the content probably belongs in the PHP docs, not here.
- **Footer pointer, every page.** Each page ends with a line like: `For ... see the [X page in the PHP docs](https://mozex.dev/docs/anthropic-php/v1/...) or the [Anthropic reference](https://platform.claude.com/docs/en/...)`.
- **Follow the `human-writing` skill.** No em dashes, no AI-flavored filler ("delve into", "leverage", "it's important to note"), mixed sentence rhythm, contractions on.
- **Match the existing voice.** Read a neighboring section before writing a new one so the tone and code-comment style are consistent.

### When in doubt

If the PHP doc change added 150+ lines, the Laravel mirror is usually 20-40 lines plus one or two Laravel-idiomatic examples. If you find yourself copying an entire section verbatim, stop and defer to the PHP docs instead.

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
