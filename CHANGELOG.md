# Changelog

All notable changes to `anthropic-php` will be documented in this file.

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
