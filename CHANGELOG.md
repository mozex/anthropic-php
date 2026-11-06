# Changelog

All notable changes to `anthropic-php` will be documented in this file.

## 1.15.0 - 2026-11-06

### What's Changed

* A Messages stream gives its text as it arrives with `text()`, and the whole message it adds up to with `finalMessage()`, the same `CreateResponse` that `create()` returns. `finalText()`, `currentMessage()` and callbacks per event kind (`onEvent()`, `onText()`, `onThinking()`, `onContentBlock()`, `onMessage()`) come with them, and all of it works on fake streams.
* `MessageAccumulator` folds stream events you read another way into the message they add up to.
* `RefusalFallbackMiddleware` falls back on streamed requests too: a refused stream goes on with the next model's events spliced onto it, behind a `fallback` block, under one `message_start`.
* The middleware takes every `fallback` block out of the history you send back, where it used to take only those at the front of a turn.
* `CreateResponse::$stop_reason` is nullable, for a message folded from a stream before its `message_delta`.

`UPGRADING.md` lists the changes that can affect code written against 1.14, such as the nullable `stop_reason` and a subclass of the Messages `StreamResponse` that declares one of its new methods.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.14.0...1.15.0

## 1.14.0 - 2026-11-04

### What's Changed

* `withStreamCompletionHandler()` on the factory reports each Messages stream's usage once it ends, fails or is dropped. The stream has `usage()`, `model()`, `completed()` and `durationMs()`.
* A response's usage has `billedInputTokens()`, `billedOutputTokens()`, `billedTokens()` and `rateLimitTokens()`, counted across `usage.iterations`.

`UPGRADING.md` lists the changes that can affect code written against 1.13, such as a transporter of your own needing `streamCompletionHandler()`.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.13.0...1.14.0

## 1.13.0 - 2026-11-02

### What's Changed

* `ClientFake` answers each call with its method's default fake until you queue a response. `preventStrayRequests()` makes an unanswered call throw instead.
* `respondTo()` answers calls by resource, method or a closure, beside the queue. `assertSentTimes()`, `assertSentInOrder()` and `sent()` are public, and under Pest `expect($fake)->toHaveSent()`, `toHaveSentTimes()`, `toHaveSentOn()`, `toHaveSentInOrder()` and `toHaveSentNothing()` work too.
* Every API exception has a `fake()` with its status, type and request ID, and `TransporterException::fake()` stands for a request that got no answer.
* `CreateStreamedResponse::fake()` takes a list of events or a `CreateResponse`, and `BatchResultResponse::fake()` a list of results. Both carry the same rate-limit headers and request ID as the other fakes.
* `Testing\Blocks` builds text, tool use, thinking and web search blocks.
* `Testing\Recorder`, a client middleware, records a test's API calls once and replays them from a file afterwards, with no key.
* `batches()->wait()` polls a batch until it ends. `BatchResponse` has `isEnded()`, and batch results have `succeeded()`, `errored()`, `canceled()` and `expired()`.
* `messages()->fits()` checks a request against a token limit, and `trimToFit()` drops the oldest turns until it fits.

`UPGRADING.md` lists the changes that can affect code written against 1.12, such as a fake with no responses no longer throwing.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.12.0...1.13.0

## 1.12.0 - 2026-11-01

### What's Changed

* Each error status throws its own subclass of `ErrorException` (`BadRequestException`, `AuthenticationException`, `BillingException`, `PermissionDeniedException`, `NotFoundException`, `ConflictException`, `RequestTooLargeException`, `UnprocessableEntityException`, `RateLimitException`, `InternalServerException`, `ApiTimeoutException`, `OverloadedException`), and an error in the middle of a stream throws the subclass of its type.
* `ErrorException` has `getRequestId()`, `getErrorDetails()` and `getRetryAfter()`, and `context()` gives each API error's request ID, status, type, `retry-after` and error code to Laravel's exception handler and the Laravel packages of Sentry and Flare.
* Connection failures throw `ConnectionException` and timeouts `TimeoutException`, both `TransporterException`s.
* `withOptions()` returns a client whose requests carry extra headers, query parameters, betas, an API version, a workspace, a user profile or a retry count; methods that take only an ID get betas this way.
* `Factory::withAuthToken()` sends a bearer token, such as a Workload Identity Federation access token; `withWorkspaceId()` sends `anthropic-workspace-id`.
* `Factory::withBaseUrl()` takes a URL the way the official SDKs read `ANTHROPIC_BASE_URL`.
* `withTimeout()` and `withConnectTimeout()` set the timeouts of the Guzzle client the factory builds.
* `Anthropic::fromEnvironment()` builds a client from `ANTHROPIC_API_KEY` or `ANTHROPIC_AUTH_TOKEN`, `ANTHROPIC_BASE_URL` and `ANTHROPIC_CUSTOM_HEADERS`.
* `Factory::withConfig()` builds a client from a config array. A misspelt key or a value of the wrong type throws, naming the key.
* `Factory::withMaxRetries()` retries connection failures, timeouts and 408, 409, 429 and 5xx responses, waiting what the API asks or backing off; retries are off by default.
* `Factory::withMiddleware()` runs code around every HTTP attempt; a middleware that throws `RetryableException` sends the request again. `Factory::withLogger()` logs each request, response and retry to a PSR-3 logger, without bodies, headers or query strings.
* `request()` sends a request to any API path, returning a `RawResponse`; `iterate()` on `models()`, `batches()` and `files()` walks every page of a list.
* `RefusalFallbackMiddleware` retries a refused Messages request on the fallback models you list, redeeming the refusal's fallback credit; streamed requests pass through for now.
* `OpenTelemetryMiddleware` traces each request with OpenTelemetry's GenAI attributes and sends the trace context.
* `Client::settings()` returns what a client sends with every request, never its key.
* The Models responses have `isRetired()`, `isRetiring()`, `isRetirementOverdue()` and `retirementDate()`.
* The fake's `withOptions()` shares its queue, and `assertSentWithOptions()` checks a request's options. `RawResponse::fake()` answers raw requests, and the list fakes walk with `iterate()`. `ClientFake::onConnection()` gives a fake per connection, with `assertSentOn()` and `assertNotSentOn()`.

#### Fixes

* A list in a query, such as the models list's `lifecycle` filter, is now sent as `key[]=`, so the API applies it.
* A mid-stream error sent as a plain string throws `ErrorException`.
* A header set twice under different capitalization sends only the later value.
* `getErrorType()` returns null for an error type that isn't a string.
* A key or token given to `Anthropic::client()`, the factory, `withConfig()` or `withOptions()` stays out of a stack trace's arguments, which error trackers such as Flare collect.

`UPGRADING.md` lists the changes that can affect code written against 1.10.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.10.0...1.12.0

## 1.10.0 - 2026-11-01

### What's Changed

* `Factory::withApiVersion()` sets the `anthropic-version` header, so you can opt into API version `2026-10-21`; the default stays `2023-06-01`.
* Responses keep every field the API sends: `toArray()` and array access return fields this release doesn't type yet and fields sent as `null`, in the API's order, so a response's content can be sent back to the API unchanged.
* Messages responses type the MCP connector blocks (`mcp_tool_use` with `server_name`, `mcp_tool_result` with `is_error`, `mcp_tool_listing`), advisor results, `toolset_name` on computer and browser tool calls, compaction `encrypted_content` and `tool_changes` (and keep `signature` in `toArray()`), the fallback `trigger`, `input_transformations`, and fallback credit (`stop_details->fallback_credit_token`, `usage->fallbackCredit`).
* Streamed Messages events type every field the non-streamed message has: a block start's `signature`, `caller`, `toolset_name`, `server_name`, `is_error` and the other block fields; a `message_delta`'s `stop_details` (the category of a streamed refusal) and `container`; `context_management` and `input_transformations` on the events; a compaction delta's `encrypted_content` and a thinking delta's `estimated_tokens`.
* Models responses type `lifecycle`, `line`, `deprecated_at` and `retires_at`, the `server_tools` capability, `xhigh` effort, the `disabled` and `between_tools` thinking types, and the beta `allowed_fallback_models` and `compaction` capability.
* A batch result's error has its `requestId`, and `toArray()` includes `request_id` beside the type and message.
* `meta()` types the organization and workspace ID headers, `retry-after` and the fast mode rate limits; they also stay in `custom`.
* Token counts type the beta `context_management.original_input_tokens` as `contextManagement`.
* Streams and batch results are read 64 KiB at a time instead of byte by byte.
* A Messages stream yields event types the client doesn't know yet instead of dropping them; `ping` is still skipped.
* `assertNotSent()` accepts a number, like `assertSent()`, and `ClientFake` accepts a PSR-7 stream for content methods such as `files()->download()`.
* Classes are no longer `final`, so you can extend them; `fake()` on a subclass of a response builds from the parent's fixture.
* The docs cover the newer API features: mid-conversation system messages and tool changes, compaction on demand, the computer and browser toolsets, programmatic tool calling, the MCP connector, the advisor tool, fast mode, fallback credit, refusal categories and streamed refusals, and the current models.

#### Fixes

* A streamed request that fails with a 4xx or 5xx, such as a 529 overload, throws `ErrorException` with the API's error type instead of `TransporterException`.
* Every response with a status of 400 or above throws `ErrorException` (`RateLimitException` for a 429), even when the body isn't the API's JSON. A proxy's HTML page or a plain-text 503 used to throw `UnserializableResponse` or a `TypeError`, and `files()->download()` returned the error page as the file. The message is the first line of the page's text.
* A tool call without parameters comes out of `toArray()` with `input` as an `ArrayObject`, which encodes as `{}`: the API rejects a replayed `"input": []`.
* Streaming an on-demand compaction no longer throws a `TypeError`: a block that arrives whole with string `content` is read as a string.
* A model or a list page without some of the documented-nullable fields no longer throws; an errored batch result builds again from its own `toArray()`.
* A response without `stop_sequence` no longer raises a warning.
* A base URI ending in `/` no longer doubles the slash in request paths.
* In `fake()` overrides, a nested item whose `type` differs from the default's replaces it, so a faked `tool_use` block no longer carries the default text.
* The tool use docs no longer tell you to skip a tool call made from code execution: such a call is paused code waiting for your result, and the docs show how to answer it.

#### Deprecated

* The Text Completions resource and `completions()` are marked deprecated: the API rejects `/v1/complete` requests on current models. Use the Messages API.

#### Upgrading

Streams are read per the SSE spec, so a hand-written fake stream needs a blank line between events. `UPGRADING.md` lists the other changes that can affect code written against 1.8.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.8.0...1.10.0

## 1.8.0 - 2026-08-01

### What's New

This release brings the response layer up to date with the Anthropic API changes shipped between April and July 2026: new usage fields, server-side fallback, compaction, and cache diagnostics.

#### New usage fields

Three additions to `usage` on Messages responses:

```php
$response->usage->outputTokensDetails?->thinkingTokens; // how many billed output tokens were reasoning
$response->usage->speed;                                // 'fast' or 'standard' on fast mode requests
$response->usage->iterations;                           // per-attempt records under the fallback and compaction betas






```
When streaming, `outputTokensDetails` arrives on the final `message_delta` event.

#### Server-side fallback (beta)

When a refused request is retried on another model (the `fallbacks` parameter with the `server-side-fallback-2026-07-01` beta header), the response now parses fully:

- `fallback` content blocks mark the handoff: `$block->from->model` and `$block->to->model`
- `usage->iterations` records every attempt: a `message` entry for a declined model, a `fallback_message` entry for the model that served the turn
- `stop_details->recommended_model` names a model to retry directly when the fallback itself was rate limited
- `stop_details->category` now covers `frontier_llm`, `reasoning_extraction`, and `general_harms` alongside `cyber` and `bio`

#### Compaction (beta)

Under the `compact-2026-01-12` beta, responses can include a `compaction` content block whose `content` is a string summary. The SDK now handles that block, the `compaction_delta` stream event, the top-level `context_management` response field, and the `stop_reason: 'compaction'` value that appears with `pause_after_compaction`. All of it was verified against live API responses, including the quirk that iteration entries under this beta carry no `model` key.

```php
foreach ($response->content as $block) {
    if ($block->type === 'compaction') {
        $block->content; // 'Summary of the conversation: ...'
    }
}

$response->context_management; // ['applied_edits' => [...]] or null






```
#### Cache diagnostics (beta)

With the `cache-diagnosis-2026-04-07` header and a `diagnostics` request parameter, the API reports where two consecutive requests diverged:

```php
$response->diagnostics?->cache_miss_reason?->type;                      // 'system_changed'
$response->diagnostics?->cache_miss_reason?->cache_missed_input_tokens; // 41850






```
On streams, `diagnostics` arrives on the `message_start` event.

#### Documentation

The [Messages](https://mozex.dev/docs/anthropic-php/v1/usage/messages), [Thinking](https://mozex.dev/docs/anthropic-php/v1/usage/thinking), [Streaming](https://mozex.dev/docs/anthropic-php/v1/usage/streaming), and [Server Tools](https://mozex.dev/docs/anthropic-php/v1/usage/server-tools) guides now cover refusal handling with fallback, compaction, cache diagnostics, thinking token usage, and the latest server tool versions (`web_search_20260318`, `web_fetch_20260318`, `code_execution_20260521`).

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.7.0...1.8.0

## 1.7.0 - 2026-04-18

### What's New

#### Files API

Upload a document once and reference it by `file_id` on later Messages calls. Useful for repeated PDFs and images, and for reading outputs produced by the code execution tool and Skills.

Five methods on `$client->files()`:

- `upload(array $parameters)`: multipart upload, returns a `FileResponse`
- `list(array $parameters = [])`: cursor-paginated listing
- `retrieveMetadata(string $fileId)`: fetch metadata for a single file
- `download(string $fileId)`: raw bytes for files produced by code execution or Skills (user-uploaded files are not downloadable)
- `delete(string $fileId)`: returns a `DeletedFileResponse`

```php
$file = $client->files()->upload([
    'file' => fopen('/path/to/doc.pdf', 'r'),
]);

$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 1024,
    'betas' => ['files-api-2025-04-14'],
    'messages' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'Summarise this.'],
            ['type' => 'document', 'source' => ['type' => 'file', 'file_id' => $file->id]],
        ],
    ]],
]);







```
Anthropic currently flags the Files endpoints as beta. The SDK auto-injects the required `anthropic-beta: files-api-2025-04-14` header on every `$client->files()` call, so you don't type the version string. When you reference a `file_id` inside a Messages call, pass `'betas' => ['files-api-2025-04-14']` on that call too; the Messages endpoint also needs the header when a file is referenced.

#### Documentation

New [Files usage guide](https://mozex.dev/docs/anthropic-php/v1/usage/files) covers upload, list, retrieve, download, delete, and Messages integration, including the per-call `betas` pattern for referencing uploaded files.

#### Testing

Every Files response DTO has a `fake()` method for use with `ClientFake`:

- `FileResponse::fake()`
- `FileListResponse::fake()`
- `DeletedFileResponse::fake()`

### What's Changed

* Add Files API resource by @mozex in https://github.com/mozex/anthropic-php/pull/17

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.6.0...1.7.0

## 1.6.0 - 2026-04-18

### What's Changed

* Add `betas` parameter for per-request beta feature opt-in by @mozex in https://github.com/mozex/anthropic-php/pull/16

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.5.0...1.6.0

## 1.5.0 - 2026-04-14

### What's Changed

#### Added

**Models API**

- Add `maxInputTokens` and `maxTokens` properties to `RetrieveResponse` (and every item returned by `ListResponse`)
- Add typed `capabilities` tree on model responses: `batch`, `citations`, `codeExecution`, `imageInput`, `pdfInput`, `structuredOutputs`, `thinking` (with `types.adaptive` and `types.enabled`), and `effort` (with `low`, `medium`, `high`, `max` levels)
- Add `capabilities.contextManagement` as a map keyed by the raw API strategy name (`clear_thinking_20251015`, `clear_tool_uses_20250919`, `compact_20260112`, and any future version), so new strategies Anthropic ships are captured automatically without a package update

**Messages API**

- Add `stop_details` on `CreateResponse` with `type`, `category`, and `explanation` — populated when `stop_reason` is `'refusal'`
- Add `caller` on `tool_use` and `server_tool_use` content blocks (`type` and `tool_id`) so direct model calls can be distinguished from calls made inside a code execution sandbox
- Add `container_upload` content block support with `file_id`
- Add `inferenceGeo` to `CreateResponseUsage` and `CreateStreamedResponseUsage`, surfacing which region handled the request (`'us'`, `'eu'`, or `null`)
- Document the new `pause_turn` and `refusal` stop reasons and the idiom for resuming paused turns

**Meta information**

- Add `priorityInputTokenLimit` and `priorityOutputTokenLimit` on `MetaInformation`, parsing the six `anthropic-priority-*` headers into typed properties instead of dropping them into the generic `custom` bucket

**Documentation**

- Ship the full documentation site at [mozex.dev/docs/anthropic-php/v1](https://mozex.dev/docs/anthropic-php/v1) with dedicated pages for every feature: introduction, configuration, messages, tool use, server tools, streaming, thinking, citations, batches, token counting, models, completions, meta information, testing, and error handling
- Every page now links back to the matching Anthropic reference on `platform.claude.com/docs/`

#### Improved

- `tool_use` / `server_tool_use` `toArray()` output now includes the `caller` object when present, so response round-trips stay lossless
- `MetaInformation` rate-limit headers (including the new priority tier) round-trip cleanly through `toArray()`

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.4.0...1.5.0

## 1.4.0 - 2026-04-01

### What's Changed

#### Added

* Add adaptive thinking support (`thinking.type: "adaptive"`) for Claude Opus 4.6 and Sonnet 4.6
* Add `display` option on thinking config (`summarized` / `omitted`) to control thinking output in responses
* Add `output_config.effort` parameter for guiding thinking depth (`max`, `high`, `medium`, `low`)
* Add `server_tool_use` content block type for server-side tools (web search, web fetch, code execution, tool search)
* Add `web_search_tool_result` content block type with search results array
* Add `web_fetch_tool_result` content block type
* Add `code_execution_tool_result`, `bash_code_execution_tool_result`, and `text_editor_code_execution_tool_result` content block types
* Add `tool_search_tool_result` content block type
* Add `tool_use_id` and `content` properties on `CreateResponseContent` for all server tool result blocks
* Add `container` field on `CreateResponse` for code execution sandbox persistence
* Add `citations` array on text content blocks, supporting all 5 citation location types: `char_location`, `page_location`, `content_block_location`, `web_search_result_location`, `search_result_location`
* Add `citations_delta` streaming support with `citation` property on `CreateStreamedResponseDelta`
* Add `webFetchRequests`, `codeExecutionRequests`, and `toolSearchRequests` to `CreateResponseUsageServerToolUse`
* Add streaming support for `server_tool_use` and server tool result blocks in `CreateStreamedResponseContentBlockStart`

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.3.3...1.4.0

## 1.3.3 - 2026-03-05

* updated model names for consistency

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.3.2...1.3.3

## 1.3.2 - 2026-03-05

* add batch response return type to client fake

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.3.1...1.3.2

## 1.3.1 - 2026-03-05

### What's Changed

#### Added

* Add dedicated `inputTokenLimit` and `outputTokenLimit` rate limit properties to `MetaInformation`
* Add `cache_creation` breakdown to usage (`CreateResponseUsageCacheCreation` with `ephemeral5mInputTokens` and `ephemeral1hInputTokens`)
* Add `serviceTier` field to usage (standard, priority, or batch)
* Add `serverToolUse` field to usage (`CreateResponseUsageServerToolUse` with `webSearchRequests`)

#### Improved

* Input/output token rate limit headers are now parsed as dedicated properties instead of falling into the generic `custom` bucket
* Usage objects now capture all fields returned by the API instead of silently dropping extended fields

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.3.0...1.3.1

## 1.3.0 - 2026-03-05

### What's Changed

#### Added

* Add Message Batches support — create, retrieve, list, cancel, delete, and stream results
* Add `BatchResponse`, `BatchListResponse`, `DeletedBatchResponse`, and `BatchResultResponse` response DTOs
* Add `BatchIndividualResponse`, `BatchResult`, and `BatchResultError` for parsing JSONL batch results
* Add `BatchResponseRequestCounts` for tracking processing, succeeded, errored, canceled, and expired counts
* Add `BatchResultResponse::fake()` for consumer testing with `ClientFake`
* Add dedicated `inputTokenLimit` and `outputTokenLimit` rate limit properties to `MetaInformation`
* Add `cache_creation` breakdown to usage (`CreateResponseUsageCacheCreation` with `ephemeral5mInputTokens` and `ephemeral1hInputTokens`)
* Add `serviceTier` field to usage (standard, priority, or batch)
* Add `serverToolUse` field to usage (`CreateResponseUsageServerToolUse` with `webSearchRequests`)

#### Improved

* Input/output token rate limit headers are now parsed as dedicated properties instead of falling into the generic `custom` bucket
* Usage objects now capture all fields returned by the API instead of silently dropping extended fields

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.2.3...1.3.0

## 1.2.3 - 2026-03-04

### What's Changed

* add count tokens support
* add models list and retrieve support

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.2.2...1.2.3

## 1.2.2 - 2026-03-04

### What's Changed

* added thinking support
* add anthropic-version header to the factory

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.2.1...1.2.2

## 1.2.1 - 2026-03-04

### What's Changed

#### Fixed

* Add missing `cache_creation_input_tokens` and `cache_read_input_tokens` to `CreateStreamedResponseUsage`

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.2.0...1.2.1

## 1.2.0 - 2026-03-04

### What's Changed

#### Added

* Add `RateLimitException` for HTTP 429 responses (extends `ErrorException` — no breaking changes)
* Add PSR-7 response access on `ErrorException` and `UnserializableResponse` via `$e->response`
* Add `MetaInformationCustom` for capturing non-standard response headers via `$meta->custom`
* Add `OverrideStrategy` enum for controlling `fake()` override merging behavior
* Add `addHeader()` method to `TransporterContract` for runtime header injection

#### Improved

* Preserve API error message on rate limit responses instead of using a generic message
* Use `(string) $response->getBody()` instead of `getContents()` for reliable stream reading
* Add `JSON_UNESCAPED_UNICODE` flag for proper Unicode handling in request payloads
* Simplify `Fakeable` trait using `array_replace_recursive`
* Fix namespace resolution in `Fakeable` `str_replace` call
* Parameterize `Streamable` trait methods for flexibility

#### Upgrading

See [UPGRADING.md](UPGRADING.md) for details. All changes are backwards compatible.

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.1.0...1.2.0

## 1.1.0 - 2024-12-21

### What's Changed

* Add tool use (function calling) support by @mozex in https://github.com/mozex/anthropic-php/pull/6
* Add cached tokens usage by @mozex in https://github.com/mozex/anthropic-php/pull/7

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.0.3...1.1.0

## 1.0.3 - 2024-12-20

* [bump dependencies](https://github.com/mozex/anthropic-php/commit/85bbeea2894bcbb7a13497afa95c3a135a99b22b)
* [add php 8.4 to workflow](https://github.com/mozex/anthropic-php/commit/39b32f5967c9482f74f71b841c48579f70d78cb4)
* [add status code to error exceptions](https://github.com/mozex/anthropic-php/commit/144447b265f20dcffea3c6fc30ba733bcd4a989d)

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.0.2...1.0.3

## 1.0.2 - 2024-08-15

### What's Changed

* Bump dependencies
* Refactoring

**Full Changelog**: https://github.com/mozex/anthropic-php/compare/1.0.1...1.0.2

## 1.0.1 - 2024-05-01

- fix client fake types

## 1.0.0 - 2024-05-01

Initial Release
