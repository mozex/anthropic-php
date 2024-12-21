[![Latest Version on Packagist](https://img.shields.io/packagist/v/mozex/anthropic-php.svg?style=flat-square)](https://packagist.org/packages/mozex/anthropic-php)
[![GitHub Tests Workflow Status](https://img.shields.io/github/actions/workflow/status/mozex/anthropic-php/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mozex/anthropic-php/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/mozex/anthropic-php.svg?style=flat-square)](https://packagist.org/packages/mozex/anthropic-php)

------
**Anthropic PHP** is a community-maintained PHP API client that allows you to interact with the [Anthropic API](https://docs.anthropic.com/claude/docs/intro-to-claude). This package is based on the excellent work of [Nuno Maduro](https://github.com/nunomaduro) and [Sandro Gehri](https://github.com/gehrisandro).

> **Note:** If you want to use the **Anthropic PHP** in Laravel, take a look at the [mozex/anthropic-laravel](https://github.com/mozex/anthropic-laravel) repository.

## Table of Contents
- [Support Us](#support-us)
- [Get Started](#get-started)
- [Usage](#usage)
  - [Messages Resource](#messages-resource)
  - [Completions Resource (Legacy)](#completions-resource-legacy)
- [Meta Information](#meta-information)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Support us

Creating and maintaining open-source projects requires significant time and effort. Your support will help enhance the project and enable further contributions to the PHP community.

Sponsorship can be made through the [GitHub Sponsors](https://github.com/sponsors/mozex) program. Just click the "**[Sponsor](https://github.com/sponsors/mozex)**" button at the top of this repository. Any amount is greatly appreciated, even a contribution as small as $1 can make a big difference and will go directly towards developing and improving this package.

Thank you for considering sponsoring. Your support truly makes a difference!

## Get Started

> **Requires [PHP 8.1+](https://php.net/releases/)**

First, install Anthropic via the [Composer](https://getcomposer.org/) package manager:

```bash
composer require mozex/anthropic-php
```

Ensure that the `php-http/discovery` composer plugin is allowed to run or install a client manually if your project does not already have a PSR-18 client integrated.
```bash
composer require guzzlehttp/guzzle
```

Then, interact with Anthropic's API:

```php
$yourApiKey = getenv('YOUR_API_KEY');
$client = Anthropic::client($yourApiKey);

$result = $client->messages()->create([
    'model' => 'claude-3-opus-20240229',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

echo $result->content[0]->text; // Hello! How can I assist you today?
```

If necessary, it is possible to configure and create a separate client.

```php
$yourApiKey = getenv('YOUR_API_KEY');

$client = Anthropic::factory()
    ->withApiKey($yourApiKey)
    ->withHttpHeader('anthropic-version', '2023-06-01')
    ->withBaseUri('anthropic.example.com/v1') // default: api.anthropic.com/v1
    ->withHttpClient($httpClient = new \GuzzleHttp\Client([])) // default: HTTP client found using PSR-18 HTTP Client Discovery
    ->withHttpHeader('X-My-Header', 'foo')
    ->withQueryParam('my-param', 'bar')
    ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $httpClient->send($request, [
        'stream' => true // Allows to provide a custom stream handler for the http client.
    ]))
    ->make();
```

## Usage

### `Messages` Resource

#### `create`

Creates a completion for structured list of input messages.

```php
$response = $client->messages()->create([
    'model' => 'claude-3-opus-20240229',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$response->id; // 'msg_01BSy0WCV7QR2adFBauynAX7'
$response->type; // 'message'
$response->role; // 'assistant'
$response->model; // 'claude-3-opus-20240229'
$response->stop_sequence; // null
$response->stop_reason; // 'end_turn'

foreach ($response->content as $result) {
    $result->type; // 'text'
    $result->text; // 'Hello! It's nice to meet you. How can I assist you today?'
}

$response->usage->inputTokens; // 10,
$response->usage->outputTokens; // 19,
$response->usage->cacheCreationInputTokens; // 0,
$response->usage->cacheReadInputTokens; // 0,

$response->toArray(); // ['id' => 'msg_01BSy0WCV7QR2adFBauynAX7', ...]
```

Creates a completion for the structured list of input messages with a tool call.

```php
$response = $client->messages()->create([
    'model' => 'claude-3-opus-20240229',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
    ],
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA'
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'The unit of temperature, either \"celsius\" or \"fahrenheit\"'
                    ]
                ],
                'required' => ['location']
            ]
        ]
    ]
]);

$response->id; // 'msg_01BSy0WCV7QR2adFBauynAX7'
$response->type; // 'message'
$response->role; // 'assistant'
$response->model; // 'claude-3-opus-20240229'
$response->stop_sequence; // null
$response->stop_reason; // 'tool_use'

$response->content[0]->type; // 'text'
$response->content[0]->text; // 'I'll help you check the current weather in San Francisco. I'll use the get_weather function, assuming San Francisco, CA as the location.'

$response->content[1]->type; // 'tool_use'
$response->content[1]->id; // 'toolu_01RnYGkgJusAzXvcySfZ2Dq7'
$response->content[1]->name; // 'get_weather'
$response->content[1]->input['location']; // 'San Francisco, CA'
$response->content[1]->input['unit']; // 'fahrenheit'

$response->usage->inputTokens; // 448,
$response->usage->outputTokens; // 87,
$response->usage->cacheCreationInputTokens; // 0,
$response->usage->cacheReadInputTokens; // 0,

$response->toArray(); // ['id' => 'msg_01BSy0WCV7QR2adFBauynAX7', ...]
```

#### `create streamed`

Creates a streamed completion for structured list of input messages.

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-3-haiku-20240307',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

foreach($stream as $response){
    $response->toArray();
}
// 1. iteration
[
    'type' => 'message_start',
    'message' => [    
        'id' => 'msg_01SX1jLtTXgtJwB2EpSRNutG',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [],
        'model' => 'claude-3-haiku-20240307',
        'stop_reason' => null,
        'stop_sequence' => null,
    ],
    'usage' => [    
        'input_tokens' => 9,
        'output_tokens' => 1,
    ]
]
// 2. iteration
[
    'type' => 'content_block_start',
    'index' => 0,
    'content_block_start' => [    
        'type' => 'type',
        'text' => '',
    ]
]
// 3. iteration
[
    'type' => 'content_block_delta',
    'index' => 0,
    'delta' => [    
        'type' => 'text_delta',
        'text' => 'Hello',
    ]
]
// 4. iteration
[
    'type' => 'content_block_delta',
    'index' => 0,
    'delta' => [    
        'type' => 'text_delta',
        'text' => '!',
    ]
]

// ...

// last iteration
[
    'type' => 'message_delta',
    'delta' => [    
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
    ],
    'usage' => [    
        'output_tokens' => 12,
    ]
]
```

Creates a streamed completion for structured list of input messages with a tool call.

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-3-haiku-20240307',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
    ],
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA'
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'The unit of temperature, either \"celsius\" or \"fahrenheit\"'
                    ]
                ],
                'required' => ['location']
            ]
        ]
    ]
]);

foreach($stream as $response){
    $response->toArray();
}
// 1. iteration
[
    'type' => 'message_start',
    'message' => [    
        'id' => 'msg_01SX1jLtTXgtJwB2EpSRNutG',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [],
        'model' => 'claude-3-haiku-20240307',
        'stop_reason' => null,
        'stop_sequence' => null,
    ],
    'usage' => [    
        'input_tokens' => 9,
        'output_tokens' => 1,
    ]
]
// 2. iteration
[
    'type' => 'content_block_start',
    'index' => 0,
    'content_block_start' => [    
        'type' => 'type',
        'text' => '',
    ]
]
// 3. iteration
[
    'type' => 'content_block_delta',
    'index' => 0,
    'delta' => [    
        'type' => 'text_delta',
        'text' => 'I',
    ]
]
// 4. iteration
[
    'type' => 'content_block_delta',
    'index' => 0,
    'delta' => [    
        'type' => 'text_delta',
        'text' => '\'ll help you check the current weather',
    ]
]

// ...

// 1. iteration of tool call
[
    'type' => 'content_block_start',
    'index' => 1,
    'content_block_start' => [    
        'id' => 'toolu_01RDFRXpbNUGrZ1xQy443s5Q',
        'type' => 'tool_use',
        'name' => 'get_weather',
        'input' => [],
    ]
]
// 2. iteration of tool call
[
    'type' => 'content_block_delta',
    'index' => 1,
    'delta' => [    
        'type' => 'input_json_delta',
        'partial_json' => '{"location',
    ]
]

// ...

// last iteration
[
    'type' => 'message_delta',
    'delta' => [    
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
    ],
    'usage' => [    
        'output_tokens' => 12,
    ]
]
```

### `Completions` Resource (Legacy)

#### `create`

Creates a completion for the provided prompt and parameters.

```php
$response = $client->completions()->create([
    'model' => 'claude-2.1',
    'prompt' => '\n\nHuman: Hello, Claude\n\nAssistant:',
    'max_tokens_to_sample' => 100,
    'temperature' => 0
]);

$response->type; // 'completion'
$response->id; // 'compl_01EKm5HZ9y6khqaSZjsX44fS'
$response->completion; // ' Hello! Nice to meet you.'
$response->stop_reason; // 'stop_sequence'
$response->model; // 'claude-2.1'
$response->stop; // '\n\nHuman:'
$response->log_id; // 'compl_01EKm5HZ9y6khqaSZjsX44fS'

$response->toArray(); // ['id' => 'compl_01EKm5HZ9y6khqaSZjsX44fS', ...]
```

#### `create streamed`

Creates a streamed completion for the provided prompt and parameters.

```php
$stream = $client->completions()->createStreamed([
    'model' => 'claude-2.1',
    'prompt' => 'Hi',
    'max_tokens_to_sample' => 70,
]);

foreach($stream as $response){
    $response->completion;
}
// 1. iteration => 'I'
// 2. iteration => ' am'
// 3. iteration => ' very'
// 4. iteration => ' excited'
// ...
```

## Meta Information

On messages response object you can access the meta information returned by the API via the `meta()` method.

```php
$response = $client->messages()->create([
    'model' => 'claude-3-sonnet-20240229',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$meta = $response->meta();

$meta->requestId; // 'req_012nTzj6kLoP8vZ1SGANvcgR'

$meta->requestLimit->limit; // 3000
$meta->requestLimit->remaining; // 2999
$meta->requestLimit->reset; // '2024-05-01T13:29:17Z'

$meta->tokenLimit->limit; // 250000
$meta->tokenLimit->remaining; // 249984
$meta->tokenLimit->reset; // '2024-05-01T13:29:17Z'
```

The `toArray()` method returns the meta information in the form originally returned by the API.

```php
$meta->toArray();

// [ 
//   'request-id' => 'req_012nTzj6kLoP8vZ1SGANvcgR',
//   'anthropic-ratelimit-requests-limit' => 3000,
//   'anthropic-ratelimit-requests-remaining' => 2999,
//   'anthropic-ratelimit-requests-reset' => '2024-05-01T13:29:17Z',
//   'anthropic-ratelimit-tokens-limit' => 250000,
//   'anthropic-ratelimit-tokens-remaining' => 249983,
//   'anthropic-ratelimit-tokens-reset' => '2024-05-01T13:29:17Z',
// ]
```

On streaming responses you can access the meta information on the reponse stream object.

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-3-sonnet-20240229',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);
    
$stream->meta(); 
```

For further details about the rates limits and what to do if you hit them visit the [Anthropic documentation](https://docs.anthropic.com/claude/reference/rate-limits).

## Troubleshooting

### Timeout

You may run into a timeout when sending requests to the API. The default timeout depends on the HTTP client used.

You can increase the timeout by configuring the HTTP client and passing in to the factory.

This example illustrates how to increase the timeout using Guzzle.

```php
Anthropic::factory()
    ->withApiKey($apiKey)
    ->withHttpHeader('anthropic-version', '2023-06-01')
    ->withHttpClient(new \GuzzleHttp\Client(['timeout' => $timeout]))
    ->make();
```

## Testing

The package provides a fake implementation of the `Anthropic\Client` class that allows you to fake the API responses.

To test your code ensure you swap the `Anthropic\Client` class with the `Anthropic\Testing\ClientFake` class in your test case.

The fake responses are returned in the order they are provided while creating the fake client.

All responses are having a `fake()` method that allows you to easily create a response object by only providing the parameters relevant for your test case.

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Responses\Completions\CreateResponse;

$client = new ClientFake([
    CreateResponse::fake([
        'completion' => 'awesome!',
    ]),
]);

$completion = $client->completions()->create([
    'model' => 'claude-2.1',
    'prompt' => '\n\nHuman: PHP is \n\nAssistant:',
    'max_tokens_to_sample' => 100,
]);

expect($completion['completion'])->toBe('awesome!');
```

In case of a streamed response you can optionally provide a resource holding the fake response data.

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Responses\Messages\CreateStreamedResponse;

$client = new ClientFake([
    CreateStreamedResponse::fake(fopen('file.txt', 'r'););
]);

$completion = $client->messages()->createStreamed([
    'model' => 'claude-3-haiku-20240307',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

expect($response->getIterator()->current())
        ->type->toBe('message_start');
```

After the requests have been sent there are various methods to ensure that the expected requests were sent:

```php
// assert completion create request was sent
$client->assertSent(Completions::class, function (string $method, array $parameters): bool {
    return $method === 'create' &&
        $parameters['model'] === 'claude-2.1' &&
        $parameters['prompt'] === 'PHP is ';
});
// or
$client->completions()->assertSent(function (string $method, array $parameters): bool {
    // ...
});

// assert 2 completion create requests were sent
$client->assertSent(Completions::class, 2);

// assert no completion create requests were sent
$client->assertNotSent(Completions::class);
// or
$client->completions()->assertNotSent();

// assert no requests were sent
$client->assertNothingSent();
```

To write tests expecting the API request to fail you can provide a `Throwable` object as the response.

```php
$client = new ClientFake([
    new \Anthropic\Exceptions\ErrorException([
        'message' => 'Overloaded',
        'type' => 'overloaded_error',
    ])
]);

// the `ErrorException` will be thrown
$completion = $client->completions()->create([
    'model' => 'claude-2.1',
    'prompt' => '\n\nHuman: PHP is \n\nAssistant:',
    'max_tokens_to_sample' => 100,
]);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mozex](https://github.com/mozex)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
