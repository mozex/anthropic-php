# Anthropic PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mozex/anthropic-php.svg?style=flat-square)](https://packagist.org/packages/mozex/anthropic-php)
[![GitHub Tests Workflow Status](https://img.shields.io/github/actions/workflow/status/mozex/anthropic-php/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mozex/anthropic-php/actions/workflows/tests.yml)
[![Docs](https://img.shields.io/badge/docs-mozex.dev-10B981?style=flat-square)](https://mozex.dev/docs/anthropic-php/v1)
[![License](https://img.shields.io/github/license/mozex/anthropic-php.svg?style=flat-square)](https://packagist.org/packages/mozex/anthropic-php)
[![Total Downloads](https://img.shields.io/packagist/dt/mozex/anthropic-php.svg?style=flat-square)](https://packagist.org/packages/mozex/anthropic-php)

A community-maintained PHP SDK for the [Anthropic API](https://platform.claude.com/docs/en/about-claude/models/overview). Send messages, stream responses, call tools, use extended thinking, search the web, execute code, process batches, and more. Works with any PSR-18 HTTP client.

> **[Read the full documentation at mozex.dev](https://mozex.dev/docs/anthropic-php/v1)**: searchable docs, version requirements, detailed changelog, and more.

> **Using Laravel?** Check out [Anthropic Laravel](https://github.com/mozex/anthropic-laravel), which wraps this SDK with service container integration, config-based setup, and a facade.

## Table of Contents

- [Introduction](https://mozex.dev/docs/anthropic-php/v1)
- Usage
  - [Messages](https://mozex.dev/docs/anthropic-php/v1/usage/messages)
  - [Streaming](https://mozex.dev/docs/anthropic-php/v1/usage/streaming)
  - [Tool Use](https://mozex.dev/docs/anthropic-php/v1/usage/tool-use)
  - [Thinking](https://mozex.dev/docs/anthropic-php/v1/usage/thinking)
  - [Server Tools](https://mozex.dev/docs/anthropic-php/v1/usage/server-tools)
  - [Citations](https://mozex.dev/docs/anthropic-php/v1/usage/citations)
  - [Token Counting](https://mozex.dev/docs/anthropic-php/v1/usage/token-counting)
  - [Models](https://mozex.dev/docs/anthropic-php/v1/usage/models)
  - [Batches](https://mozex.dev/docs/anthropic-php/v1/usage/batches)
  - [Completions](https://mozex.dev/docs/anthropic-php/v1/usage/completions)
- Reference
  - [Configuration](https://mozex.dev/docs/anthropic-php/v1/reference/configuration)
  - [Error Handling](https://mozex.dev/docs/anthropic-php/v1/reference/error-handling)
  - [Meta Information](https://mozex.dev/docs/anthropic-php/v1/reference/meta-information)
  - [Testing](https://mozex.dev/docs/anthropic-php/v1/reference/testing)

## Support This Project

I maintain this package along with [several other open-source PHP packages](https://mozex.dev/docs) used by thousands of developers every day.

If my packages save you time or help your business, consider [**sponsoring my work on GitHub Sponsors**](https://github.com/sponsors/mozex). Your support lets me keep these packages updated, respond to issues quickly, and ship new features.

Business sponsors get logo placement in package READMEs. [**See sponsorship tiers →**](https://github.com/sponsors/mozex)

## Why This Package

**Built-in test client.** Swap `Anthropic\Client` with `ClientFake` in your tests, queue fake responses, and assert exactly which requests were sent. No HTTP mocking libraries needed, no test server to run. [See the testing docs →](https://mozex.dev/docs/anthropic-php/v1/reference/testing)

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Responses\Messages\CreateResponse;

$client = new ClientFake([
    CreateResponse::fake([
        'content' => [['type' => 'text', 'text' => 'Paris is the capital of France.']],
    ]),
]);

$response = $client->messages()->create([...]);

$client->assertSent(Messages::class, function (string $method, array $parameters): bool {
    return $parameters['model'] === 'claude-sonnet-4-6';
});
```

**Forward-compatible.** Parameters pass through to the API as-is. When Anthropic ships a new feature (a new tool type, a new thinking mode, a new parameter), it works in your code the same day. You don't wait for an SDK release.

**Typed, immutable responses.** Every response is a `readonly` PHP object with typed properties. Access `$response->usage->inputTokens`, not `$response['usage']['input_tokens']`. Full IDE autocompletion, no guessing.

**Rate limits on every response.** Call `$response->meta()` on any response (including streams and batch results) to get your current request limits, token limits, and reset times.

**Any HTTP client.** Built on PSR-18, so it works with Guzzle, Symfony HTTP Client, Buzz, or whatever your project already uses. No vendor lock-in.

## Installation

> **Requires [PHP 8.2+](https://www.php.net/releases/)** - see [all version requirements](https://mozex.dev/docs/anthropic-php/v1/requirements)

```bash
composer require mozex/anthropic-php
```

The included `php-http/discovery` plugin finds and installs a compatible PSR-18 HTTP client automatically. If you want to use a specific one (like Guzzle or Symfony), see [Configuration](https://mozex.dev/docs/anthropic-php/v1/reference/configuration).

## Quick Start

Create a client, send a message, read the response:

```php
$client = Anthropic::client('your-api-key');

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

echo $response->content[0]->text; // Hello! How can I assist you today?
```

### Streaming

Print text as it arrives:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Tell me a short story.'],
    ],
]);

foreach ($stream as $response) {
    if ($response->type === 'content_block_delta'
        && $response->delta->type === 'text_delta') {
        echo $response->delta->text;
    }
}
```

### Tool Use

Give Claude tools to call, execute them in your code, send results back:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
                'required' => ['location'],
            ],
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather in San Francisco?'],
    ],
]);

$response->content[1]->name;              // 'get_weather'
$response->content[1]->input['location']; // 'San Francisco'
```

### Extended Thinking

Let Claude reason through complex problems before answering:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 16000,
    'thinking' => ['type' => 'adaptive'],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the GCD of 1071 and 462?'],
    ],
]);

// Thinking block with Claude's reasoning process
$response->content[0]->thinking; // 'Using the Euclidean algorithm...'
// Final answer
$response->content[1]->text;    // 'The GCD of 1071 and 462 is 21.'
```

### Configuration

For custom base URIs, timeouts, or HTTP clients, use the factory:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withBaseUri('anthropic.example.com/v1')
    ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
    ->withHttpHeader('X-Custom-Header', 'value')
    ->make();
```

The [full documentation](https://mozex.dev/docs/anthropic-php/v1) covers every feature in detail: [web search and code execution](https://mozex.dev/docs/anthropic-php/v1/usage/server-tools), [document citations](https://mozex.dev/docs/anthropic-php/v1/usage/citations), [token counting](https://mozex.dev/docs/anthropic-php/v1/usage/token-counting), [batch processing](https://mozex.dev/docs/anthropic-php/v1/usage/batches), [error handling](https://mozex.dev/docs/anthropic-php/v1/reference/error-handling), [rate limits](https://mozex.dev/docs/anthropic-php/v1/reference/meta-information), and [more](https://mozex.dev/docs/anthropic-php/v1).

## Resources

Visit the [documentation site](https://mozex.dev/docs/anthropic-php/v1) for searchable docs auto-updated from this repository.

- **[AI Integration](https://mozex.dev/docs/anthropic-php/v1/ai-integration)**: Use this package with AI coding assistants via Context7 and Laravel Boost
- **[Requirements](https://mozex.dev/docs/anthropic-php/v1/requirements)**: PHP and dependency versions
- **[Changelog](https://mozex.dev/docs/anthropic-php/v1/changelog)**: Release history with linked pull requests and diffs
- **[Contributing](https://mozex.dev/docs/anthropic-php/v1/contributing)**: Development setup, code quality, and PR guidelines
- **[Questions & Issues](https://mozex.dev/docs/anthropic-php/v1/questions-and-issues)**: Bug reports, feature requests, and help
- **[Security](mailto:hello@mozex.dev)**: Report vulnerabilities directly via email

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
