# Changelog

All notable changes to `anthropic-php` will be documented in this file.

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
