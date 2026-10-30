# anthropic-php

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
./vendor/bin/pest tests/Resources/MessagesTest.php
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
| `docs/usage/files.md` | `docs/usage/files.md` |
| `docs/usage/token-counting.md` | `docs/usage/token-counting.md` |
| `docs/usage/completions.md` | `docs/usage/completions.md` |
| `docs/usage/raw-requests.md` | `docs/usage/raw-requests.md` |
| `docs/reference/meta-information.md` | `docs/reference/meta-information.md` |
| `docs/reference/error-handling.md` | `docs/reference/error-handling.md` |
| `docs/reference/testing.md` | `docs/reference/testing.md` |
| `docs/reference/configuration.md` | `docs/reference/configuration.md` |

### What belongs in the Laravel docs vs what to defer

**Add (or update) on the Laravel side when:**
- The feature has a natural Laravel idiom — `Log`, `Cache`, `Queue`/`ShouldQueue`, `Storage`, Eloquent, `auth()`, `config()`, controllers, form requests, middleware.
- An existing Laravel example would be subtly wrong without the new field. The `tool_use` dispatcher keyed on the (`toolset_name`, `name`) pair is the canonical example: dispatch on `name` alone and a custom tool sharing a computer or browser member's name runs in its place. (A `tool_use` whose `caller` names code execution is a paused programmatic call: the dispatcher runs it too, and answers it in a message of `tool_result` blocks only.)
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
- **Plain, human prose.** No em dashes, no AI-flavored filler ("delve into", "leverage", "it's important to note"), mixed sentence rhythm, contractions on.
- **Match the existing voice.** Read a neighboring section before writing a new one so the tone and code-comment style are consistent.

### When in doubt

If the PHP doc change added 150+ lines, the Laravel mirror is usually 20-40 lines plus one or two Laravel-idiomatic examples. If you find yourself copying an entire section verbatim, stop and defer to the PHP docs instead.

## Architecture

Layered design with strict dependency boundaries enforced by architecture tests (`tests/ArchTest.php`):

- **`Contracts/`** -- Interfaces for all major components (Client, Transporter, Resources, Responses)
- **`Resources/`** -- API endpoint implementations (Messages, Completions, Models, Batches, Files). Use `Transportable` and `Streamable` traits. May only depend on Contracts, ValueObjects, Exceptions, and Responses
- **`Responses/`** -- Immutable readonly DTOs with static `from()` factory methods and `toArray()`. Implement `ResponseContract`. May not depend on Resources or Transporters
- **`ValueObjects/`** -- Immutable configuration objects (ApiKey, BaseUri, Headers, Payload, etc.)
- **`Transporters/`** -- HTTP layer (`HttpTransporter`) using PSR-18 client discovery
- **`Enums/`** -- Must be enums only
- **`Exceptions/`** -- Must implement `Throwable`
- **`Testing/`** -- `ClientFake` and fake responses for consumer testing. Excluded from PHPStan analysis

**Entry points:** `Anthropic::client($apiKey)` for quick setup, `Anthropic::factory()` for configurable builder.

**Version and base URI:** `withApiVersion()` sets `anthropic-version` (default `2023-06-01`; a blank value keeps it, and a `withHttpHeader('anthropic-version', ...)` still wins, as custom headers are applied last). `withBaseUri()` takes the full API root, `/v1` included, and `BaseUri` only drops a trailing slash: it never adds a path, so a proxy serving the API at its root keeps working. `ANTHROPIC_BASE_URL` means something else: the official SDKs read it as the URL without `/v1` and always append `/v1/...`, path or not (`https://openrouter.ai/api` becomes `.../api/v1/messages`), so code that reads it calls `withBaseUrl()`, which appends `/v1` unless the URL already ends in it.

**Per-request options:** `Client::withOptions()` builds a new transporter through `TransporterContract::withOptions()`, never mutating the shared one; `RequestOptions` (`@internal`) validates the seven keys (unknown ones throw) and merges betas into any `anthropic-beta` header already set. `ClientFake::withOptions()` returns a clone whose `root` holds the queue and the recorded requests for every view; each request records its options for `assertSentWithOptions()`. `Factory::withBaseUrl()` is the one place the official SDKs' `ANTHROPIC_BASE_URL` meaning lives (`fromEnvironment()` and the wrapper use it); `withTimeout()`/`withConnectTimeout()` build a Guzzle client and throw beside `withHttpClient()`.

**Retries and middleware:** `HttpTransporter::send()` runs the middleware pipeline (the first added outermost) around `sendOnce()`, which returns a response for every status (Guzzle's streamed `BadResponseException` unwrapped) and throws only when none came back; the loop retries a `ConnectionException`, a `RetryableException` and a response `shouldRetry()` accepts (a status of 400 or more only: `x-should-retry`, else 408, 409, 429, 5xx) while attempts are left and the body rewinds, closing the retried response's body first, then callers run `throwIfError()`. Delays: `retry-after-ms`, then `ErrorException::retryAfterSeconds()`, when positive (zero or a past date backs off, as in the official SDKs, which set no upper cap), else `min(0.5 * 2^n, 8)` less up to 25%. The sleep is injectable (`withRetrySleep()`), so no test sleeps. `withMiddleware()` takes any callable and stores it as a `Closure`. The logger gets no body, header or query string.

**Raw requests and pagination:** `Client::request()` goes through the `Raw` resource (no contract; the fake records under `Raw::class`), `Payload::raw()` (GET and DELETE parameters in the query, POST and PUT in a JSON body; `ResourceUri::raw()` drops a leading `/` and `v1/`) and `TransporterContract::requestRaw()`, which decodes only an `application/json` or `+json` body that is an object or array and keeps any other as text. `RawTestResource` builds the payload first, so the fake rejects a method the client would. A top-level query list (int keys, gaps included) goes out as `key[]=` (`Payload::buildQuery()`; the API ignores `key[0]=`); a list nested in a map keeps `http_build_query()`'s indexes. `iterate()` on each paging list method is the `Resources\Concerns\Paginates` trait, which the test resources use too, over their own `list()`: a page's `next_page` becomes `page`, else while `has_more` its `last_id` becomes `after_id` (or `first_id` becomes `before_id` when the first call had one), read from `toArray()`, so a list DTO needs no cursor property for it; a cursor equal to the one just sent ends the walk.

**Completions is deprecated:** the resource, `CompletionsContract` and both clients' `completions()` carry `@deprecated`, and PHPStan's deprecation rules fail any caller in `src` outside `src/Testing` that isn't deprecated itself. It gets no new features.

**Request flow:** Parameters pass through as-is to the API. `Payload::create()` wraps them, `HttpTransporter` JSON-encodes and sends them. No parameter validation or transformation happens in this client. This means new API parameters (like `thinking.type`, `output_config.effort`) work automatically without code changes on the request side. New features usually only need response DTO updates, tests, and documentation.

**Errors:** `HttpTransporter::throwIfError()` throws for every status of 400 and above, whatever the body: the API's `error` object when there is one (with the body's top-level `request_id` copied into it), otherwise the status and the first line of the body's text (`RateLimitException` for any 429). `ErrorException::forStatus()` picks the subclass by status (400 `BadRequestException` ... 529 `OverloadedException`, other 5xx `InternalServerException`, other 4xx a plain `ErrorException`); a mid-stream `error` event goes through `ErrorException::forType()`, by its error type, since its status is 200. Every subclass extends `ErrorException`, so existing catches keep working. Guzzle's streamed requests throw on 4xx and 5xx, so `sendRequest()` unwraps any `BadResponseException` into the same check; any other client exception is a `TimeoutException` when its message says it timed out, a `ConnectionException` for a PSR-18 `NetworkExceptionInterface` or a Guzzle `RequestException` without a response, else a `TransporterException`.

**Streams:** every stream reader uses the `Responses\Concerns\ReadsStreams` trait: `readLines()` (64 KiB reads, line endings removed, the unread rest kept on the instance so a second iteration continues where the first stopped) for JSONL, `readEvents()` (SSE: `data:` lines joined, dispatched at a blank line or the end of the stream) for event streams. The Messages reader yields every event type except `ping`, and stops at `message_stop` without yielding it; the Completions reader yields only `completion` events, since its DTO needs a completion's keys.

## Code Conventions

- All files use `declare(strict_types=1)`
- No class is `final`: users extend package classes. Response classes have `readonly` properties, private constructors, and static `from()` factories
- The fake traits in `src/Testing/Responses/Concerns` look fixtures up by `self::class`, and the plain `Fakeable` ones return `self`, so a subclass of a response still fakes from its parent's fixture (`tests/Testing/SubclassFakeTest.php`). `src/Testing` is outside PHPStan, so only that test guards it
- `fake()` overrides go through `OverrideStrategy::apply()`: `Merge` matches `array_replace_recursive()` except that a nested item whose `type` differs from the fixture's replaces it whole (`tests/Testing/FakeOverrideTest.php`)
- Responses never drop what the API sent. Every DTO keeps the array it was built from in a `protected readonly array $attributes`, typed with `from()`'s `@param` shape, and `toArray()` returns `array_replace($this->attributes, $known)`: the payload's keys in its order, the typed fields re-serialized, and no key the API didn't send except those `toArray()` already emitted (a replayed compaction block with an extra key is a 400). A key `from()` reads under another name is unset from the stored copy (`content_block`, `message.usage` on `message_start`, the `error` of a batch result that isn't `errored`). An errored batch result's `error` is replaced in place by its flattened form: the API wraps it as `{type: "error", error: {type, message}, request_id}`, and the flattened form is the inner error with every wrapper key but `type` beside it; `from()` takes both forms. A field newer than the 1.8.0 shapes stays nullable and is re-serialized only when the payload has it, so an older payload's `toArray()` gains no key. The `@return` shapes are widened to what PHPStan infers for the `array_replace()`; a `@var` or `array<string, mixed>` on the stored copy breaks them (`tests/Responses/UnknownFieldsTest.php`, `tests/Responses/Messages/DocsExamplesTest.php`)
- An empty `input` on a `tool_use`, `server_tool_use` or `mcp_tool_use` block comes out of `toArray()` as an `ArrayObject`, which encodes as `{}`: the client decodes JSON into arrays, and the API rejects a replayed `"input": []`. The typed `$block->input` stays `[]`, and `from()` turns the `ArrayObject` back into an array, so `from($response->toArray())` and `fake($response->toArray())` keep working
- `MetaInformation` reads every rate-limit family through `rateLimit()`. A header that gains a typed property stays in `custom` too (it isn't added to `$knownHeaders`), since code already reads it there; `toArray()` then has it under its header name and in `custom`. `retry-after` is typed only as whole seconds; an HTTP date stays a string in `custom`
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

Every test file ends in `Test.php`: `phpunit.xml.dist` lists `tests` without a suffix, so PHPUnit's default `Test.php` applies and any other file there is silently skipped. Only `tests/Pest.php` and `tests/Helpers/` (which Pest loads itself) are exempt; a test in `tests/ArchTest.php` fails on any other PHP file without the suffix.

Tests use **Pest** syntax: `test('description', function () { ... })` with `expect()` assertions.

Three mock helpers defined in `tests/Pest.php`:
- `mockClient()` -- mocks transporter for object responses (verifies HTTP method, path, and request body match)
- `mockContentClient()` -- mocks transporter for content string responses
- `mockStreamClient()` -- mocks transporter for streamed responses

### Fixtures

Test fixture functions live in `tests/Helpers/` as PHP files returning arrays:
- `tests/Helpers/Message.php` -- Messages resource fixtures (`messagesCompletion()`, `messagesCompletionWithThinking()`, etc.)
- `tests/Helpers/MessageDocsExamples.php` -- Messages responses from the docs as JSON text, so `{}` and `[]` stay apart; `DocsExamplesTest` round-trips each through `toArray()`
- `tests/Helpers/ModelDocsExamples.php`, `tests/Helpers/BatchDocsExamples.php` -- Models and Message Batches responses from the docs, the same way
- `tests/Helpers/DocsExamples.php` -- the comparison helpers the docs-example tests share (`docsExampleJson()` compares as key-sorted JSON)
- `tests/Helpers/Completion.php` -- Completions resource fixtures
- `tests/Helpers/Batches.php` -- Batches resource fixtures
- `tests/Helpers/Files.php` -- Files resource fixtures
- `tests/Helpers/Models.php` -- Models resource fixtures
- `tests/Helpers/Meta.php` -- Meta/header fixtures

Streaming fixtures are SSE text files in `tests/Helpers/Streams/` (opened with `fopen()` and wrapped in a PSR-7 Stream). `tests/Helpers/MessageStreamDocsExamples.php` holds Messages streams from the docs as SSE text (the raw streams the streaming page shows, and the documented blocks and event payloads composed into events, marked); `StreamDocsExamplesTest` checks that every event's `toArray()` keeps every value its payload sent.

For consumer-facing tests, use `ClientFake` with `CreateResponse::fake()`.
