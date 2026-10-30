<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Factory;
use Anthropic\Resources\Messages;
use Anthropic\Resources\Models;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\ValueObjects\Transporter\Headers;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\ExpectationFailedException;
use Psr\Http\Message\RequestInterface;

/**
 * A Guzzle client that answers with these responses in turn and records what it sent.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function optionsHttpClient(array &$history, Response ...$responses): GuzzleClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new GuzzleClient(['handler' => $stack]);
}

/**
 * A factory whose Guzzle client answers every request with a docs model object and records what it sent.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function optionsFactory(array &$history, int $responses = 4): Factory
{
    $model = new Response(200, ['Content-Type' => 'application/json'], json_encode(modelRetrieve()));

    return Anthropic::factory()->withApiKey('foo')->withHttpClient(optionsHttpClient($history, ...array_fill(0, $responses, $model)));
}

/**
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function sentRequest(array $history, int $index = 0): RequestInterface
{
    return $history[$index]['request'];
}

/**
 * Reads the transporter inside a client built by the factory.
 */
function transporterOf(Client $client): object
{
    return (fn () => $this->transporter)->call($client);
}

/**
 * Reads the HTTP client inside a client built by the factory.
 */
function httpClientOf(Client $client): object
{
    return (fn () => $this->client)->call(transporterOf($client));
}

/**
 * Reads the headers a client built by the factory sends on every request.
 *
 * @return array<string, string>
 */
function headersOf(Client $client): array
{
    return (fn () => $this->headers->toArray())->call(transporterOf($client));
}

test('options reach the request without touching the client they came from', function () {
    $history = [];
    $client = optionsFactory($history)->withHttpHeader('anthropic-beta', 'files-api-2025-04-14')->make();

    $tenant = $client->withOptions([
        // composed: no docs example of these headers and query parameters
        'headers' => ['X-Tenant' => 'acme'],
        'query' => ['trace' => '1'],
        'betas' => ['compact-2026-09-04'],
        'api_version' => '2026-10-21',
        'workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ',
        'user_profile_id' => 'uprof_01',
    ]);

    $tenant->models()->retrieve('claude-sonnet-5-5');
    $client->models()->retrieve('claude-sonnet-5-5');

    $withOptions = sentRequest($history);
    $without = sentRequest($history, 1);

    expect($withOptions->getHeaderLine('X-Tenant'))->toBe('acme')
        ->and($withOptions->getUri()->getQuery())->toBe('trace=1')
        ->and($withOptions->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14,compact-2026-09-04')
        ->and($withOptions->getHeaderLine('anthropic-version'))->toBe('2026-10-21')
        ->and($withOptions->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ')
        // composed: no docs example of a user profile ID
        ->and($withOptions->getHeaderLine('anthropic-user-profile-id'))->toBe('uprof_01')
        ->and($without->hasHeader('X-Tenant'))->toBeFalse()
        ->and($without->getUri()->getQuery())->toBe('')
        ->and($without->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14')
        ->and($without->getHeaderLine('anthropic-version'))->toBe('2023-06-01')
        ->and($without->hasHeader('anthropic-workspace-id'))->toBeFalse();
});

test('betas from options join the betas of a single request', function () {
    $history = [];
    $http = optionsHttpClient($history, new Response(200, ['Content-Type' => 'application/json'], json_encode(messagesCompletion())));
    $client = Anthropic::factory()->withApiKey('foo')->withHttpClient($http)->make();

    $client->withOptions(['betas' => ['compact-2026-09-04']])->messages()->create([
        'model' => 'claude-sonnet-5-5',
        'max_tokens' => 1024,
        'betas' => ['context-management-2025-06-27', 'compact-2026-09-04'],
        'messages' => [['role' => 'user', 'content' => 'Hello']],
    ]);

    expect(sentRequest($history)->getHeaderLine('anthropic-beta'))->toBe('compact-2026-09-04,context-management-2025-06-27');
});

test('a method that takes only an id gets betas through options', function () {
    $history = [];
    $client = optionsFactory($history)->make();

    $client->withOptions(['betas' => ['server-side-fallback-2026-07-01']])->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->getHeaderLine('anthropic-beta'))->toBe('server-side-fallback-2026-07-01');
});

test('options of a client with options merge, the later winning', function () {
    $history = [];
    $client = optionsFactory($history)->make();

    $client->withOptions(['headers' => ['X-A' => '1'], 'api_version' => '2026-10-21'])
        ->withOptions(['headers' => ['X-B' => '2'], 'api_version' => '2023-06-01'])
        ->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->getHeaderLine('X-A'))->toBe('1')
        ->and(sentRequest($history)->getHeaderLine('X-B'))->toBe('2')
        ->and(sentRequest($history)->getHeaderLine('anthropic-version'))->toBe('2023-06-01');
});

test('unknown or malformed options are rejected', function (array $options, string $message) {
    $client = Anthropic::client('foo');

    expect(fn () => $client->withOptions($options))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'an unknown key' => [['timeout' => 30], 'Unknown request options: timeout'],
    'headers not a map' => [['headers' => 'X-A: 1'], 'The [headers] request option must be an array.'],
    'a header value not a string' => [['headers' => ['X-A' => 1]], 'must map names to strings'],
    'betas not strings' => [['betas' => [1]], 'must be a list of strings'],
    'a blank workspace id' => [['workspace_id' => ' '], 'must be a non-empty string'],
]);

test('the fake returned by withOptions shares the queue and records its options', function () {
    $fake = new ClientFake([CreateResponse::fake(), RetrieveResponse::fake()]);

    $tenant = $fake->withOptions(['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ', 'betas' => ['compact-2026-09-04']]);

    $tenant->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => []]);
    $fake->models()->retrieve('claude-sonnet-5-5');

    $fake->assertSent(Messages::class, 1);
    $fake->assertSent(Models::class, 1);
    $tenant->assertSent(Models::class, 1);
    $fake->assertSentWithOptions(Messages::class, ['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ']);
    $tenant->messages()->assertSentWithOptions(['betas' => ['compact-2026-09-04']]);

    expect(fn () => $fake->assertSentWithOptions(Models::class, ['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ']))
        ->toThrow(ExpectationFailedException::class);
});

test('responses added through a withOptions fake reach the shared queue', function () {
    $fake = new ClientFake;
    $tenant = $fake->withOptions(['headers' => ['X-Tenant' => 'acme']]);

    $tenant->addResponses([CreateResponse::fake()]);

    expect($fake->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => []]))->toBeInstanceOf(CreateResponse::class);

    $tenant->assertSent(Messages::class, 1);
});

test('the fake rejects unknown options like the client', function () {
    expect(fn () => (new ClientFake)->withOptions(['timeout' => 30]))->toThrow(InvalidArgumentException::class);
});

test('an auth token is sent as a bearer token, beside an api key when both are set', function () {
    $history = [];
    $http = optionsHttpClient($history, ...array_fill(0, 2, new Response(200, ['Content-Type' => 'application/json'], json_encode(modelRetrieve()))));

    // composed: the authentication docs show no token value
    Anthropic::factory()->withAuthToken(' sk-ant-oat01-token ')->withHttpClient($http)->make()->models()->retrieve('claude-sonnet-5-5');
    Anthropic::factory()->withApiKey('foo')->withAuthToken('sk-ant-oat01-token')->withHttpClient($http)->make()->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->getHeaderLine('Authorization'))->toBe('Bearer sk-ant-oat01-token')
        ->and(sentRequest($history)->hasHeader('x-api-key'))->toBeFalse()
        ->and(sentRequest($history, 1)->getHeaderLine('Authorization'))->toBe('Bearer sk-ant-oat01-token')
        ->and(sentRequest($history, 1)->getHeaderLine('x-api-key'))->toBe('foo');
});

test('a workspace id goes out on every request', function () {
    $history = [];
    optionsFactory($history)->withWorkspaceId('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ')->make()->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ');
});

test('a base url gets the api path the way the official sdks read it', function (string $baseUrl, string $path) {
    $history = [];
    optionsFactory($history)->withBaseUrl($baseUrl)->make()->models()->retrieve('claude-sonnet-5-5');

    expect((string) sentRequest($history)->getUri())->toBe($path);
})->with([
    'a host' => ['https://api.anthropic.com', 'https://api.anthropic.com/v1/models/claude-sonnet-5-5'],
    'a trailing slash' => ['https://api.anthropic.com/', 'https://api.anthropic.com/v1/models/claude-sonnet-5-5'],
    'a gateway path' => [' https://openrouter.ai/api ', 'https://openrouter.ai/api/v1/models/claude-sonnet-5-5'],
    'already ending in /v1' => ['https://gateway.example.com/anthropic/v1/', 'https://gateway.example.com/anthropic/v1/models/claude-sonnet-5-5'],
    'blank keeps the default' => ['  ', 'https://api.anthropic.com/v1/models/claude-sonnet-5-5'],
]);

test('the timeouts build a guzzle client with them', function () {
    $http = httpClientOf(Anthropic::factory()->withApiKey('foo')->withTimeout(120)->withConnectTimeout(2.5)->make());
    $onlyConnect = httpClientOf(Anthropic::factory()->withApiKey('foo')->withConnectTimeout(5)->make());

    expect($http)->toBeInstanceOf(GuzzleClient::class)
        ->and($http->getConfig('timeout'))->toBe(120)
        ->and($http->getConfig('connect_timeout'))->toBe(2.5)
        ->and($onlyConnect->getConfig('timeout'))->toBeNull()
        ->and($onlyConnect->getConfig('connect_timeout'))->toBe(5);
});

test('a timeout with a client of your own throws', function () {
    expect(fn () => Anthropic::factory()->withApiKey('foo')->withHttpClient(new GuzzleClient)->withTimeout(30)->make())
        ->toThrow(InvalidArgumentException::class, 'set the timeouts on the client you passed to withHttpClient()');
});

describe('fromEnvironment', function () {
    $names = ['ANTHROPIC_API_KEY', 'ANTHROPIC_AUTH_TOKEN', 'ANTHROPIC_BASE_URL', 'ANTHROPIC_CUSTOM_HEADERS'];

    beforeEach(function () use ($names) {
        $this->saved = [];

        foreach ($names as $name) {
            $this->saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    });

    afterEach(function () {
        foreach ($this->saved as $name => [$env, $server, $process]) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            if ($env !== null) {
                $_ENV[$name] = $env;
            }

            if ($server !== null) {
                $_SERVER[$name] = $server;
            }

            if ($process !== false) {
                putenv("{$name}={$process}");
            }
        }
    });

    test('it reads the key, the base url and the custom headers', function () {
        $_ENV['ANTHROPIC_API_KEY'] = ' foo ';
        $_ENV['ANTHROPIC_BASE_URL'] = 'https://openrouter.ai/api';
        // composed: no docs example of the variable's value
        $_ENV['ANTHROPIC_CUSTOM_HEADERS'] = "X-Tenant: acme\r\nX-Trace:  abc:def \nnot a header\n: no name";

        $client = Anthropic::fromEnvironment();
        $headers = headersOf($client);
        $baseUri = (fn () => $this->baseUri->toString())->call(transporterOf($client));

        expect($headers['x-api-key'])->toBe('foo')
            ->and($headers['X-Tenant'])->toBe('acme')
            ->and($headers['X-Trace'])->toBe('abc:def')
            ->and($headers)->not->toHaveKey('not a header')
            ->and($baseUri)->toBe('https://openrouter.ai/api/v1/');
    });

    test('the api key wins over an auth token, as in the official sdks', function () {
        $_ENV['ANTHROPIC_API_KEY'] = 'foo';
        $_ENV['ANTHROPIC_AUTH_TOKEN'] = 'sk-ant-oat01-token';

        $headers = headersOf(Anthropic::fromEnvironment());

        expect($headers['x-api-key'])->toBe('foo')
            ->and($headers)->not->toHaveKey('Authorization');
    });

    test('a blank value falls through to the next source', function () {
        $_ENV['ANTHROPIC_API_KEY'] = ' ';
        $_SERVER['ANTHROPIC_API_KEY'] = 'from-server';

        $headers = headersOf(Anthropic::fromEnvironment());

        expect($headers['x-api-key'])->toBe('from-server');
    });

    test('it reads an auth token from the process environment', function () {
        putenv('ANTHROPIC_AUTH_TOKEN=sk-ant-oat01-token');

        $headers = headersOf(Anthropic::fromEnvironment());

        expect($headers['Authorization'])->toBe('Bearer sk-ant-oat01-token')
            ->and($headers)->not->toHaveKey('x-api-key');
    });

    test('it throws without a key or a token', function () {
        $_ENV['ANTHROPIC_API_KEY'] = '  ';

        expect(fn () => Anthropic::fromEnvironment())->toThrow(InvalidArgumentException::class, 'Set ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN');
    });
});

test('a factory with timeouts builds a fresh client on every make, with the timeouts set then', function () {
    $factory = Anthropic::factory()->withApiKey('foo')->withTimeout(30);

    $first = httpClientOf($factory->make());
    $second = httpClientOf($factory->withTimeout(60)->make());

    expect($first->getConfig('timeout'))->toBe(30)
        ->and($second->getConfig('timeout'))->toBe(60)
        ->and($second)->not->toBe($first);
});

test('a connect timeout with a client of your own throws too', function () {
    expect(fn () => Anthropic::factory()->withApiKey('foo')->withHttpClient(new GuzzleClient)->withConnectTimeout(5)->make())
        ->toThrow(InvalidArgumentException::class);
});

test('an option replaces a header the factory set under another spelling', function () {
    $history = [];
    optionsFactory($history)->withHttpHeader('Anthropic-Version', '2023-06-01')->make()
        ->withOptions(['api_version' => '2026-10-21'])
        ->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->getHeader('anthropic-version'))->toBe(['2026-10-21']);
});

test('a blank workspace id or auth token sends no header', function () {
    $history = [];
    optionsFactory($history)->withWorkspaceId('  ')->withAuthToken(' ')->make()->models()->retrieve('claude-sonnet-5-5');

    expect(sentRequest($history)->hasHeader('anthropic-workspace-id'))->toBeFalse()
        ->and(sentRequest($history)->hasHeader('Authorization'))->toBeFalse();
});

test('a stream through a client with options carries them', function () {
    $history = [];
    $http = optionsHttpClient($history, new Response(200, ['Content-Type' => 'text/event-stream'], fopen(__DIR__.'/Helpers/Streams/MessagesCompletionCreate.txt', 'r')));
    $client = Anthropic::factory()->withApiKey('foo')->withHttpClient($http)->make();

    iterator_to_array($client->withOptions(['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ'])->messages()->createStreamed([
        'model' => 'claude-sonnet-5-5',
        'max_tokens' => 1024,
        'messages' => [['role' => 'user', 'content' => 'Hello']],
    ]));

    expect(sentRequest($history)->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ');
});

test('the fake matches options whatever their spelling, and nested subsets', function () {
    $fake = new ClientFake([CreateResponse::fake()]);

    $fake->withOptions(['betas' => [' compact-2026-09-04 '], 'headers' => ['X-Tenant' => 'acme', 'X-Trace' => 'abc']])
        ->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => []]);

    $fake->assertSentWithOptions(Messages::class, ['betas' => ['compact-2026-09-04']]);
    $fake->assertSentWithOptions(Messages::class, ['headers' => ['x-tenant' => 'acme']]);

    expect(fn () => $fake->assertSentWithOptions(Messages::class, []))->toThrow(\InvalidArgumentException::class);
});

test('a chained fake merges its options and shares assertNothingSent', function () {
    $fake = new ClientFake([CreateResponse::fake()]);
    $chained = $fake->withOptions(['headers' => ['X-A' => '1']])->withOptions(['headers' => ['X-B' => '2'], 'api_version' => '2026-10-21']);

    $chained->assertNothingSent();

    $chained->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => []]);

    $fake->assertSentWithOptions(Messages::class, ['headers' => ['X-A' => '1', 'X-B' => '2'], 'api_version' => '2026-10-21']);

    expect(fn () => $fake->assertNothingSent())->toThrow(ExpectationFailedException::class);
});

test('betas join an anthropic-beta header of any spelling', function () {
    $headers = Headers::create()
        ->withCustomHeader('Anthropic-Beta', 'files-api-2025-04-14, compact-2026-09-04')
        ->withBetas(['compact-2026-09-04', 'context-management-2025-06-27']);

    expect($headers->toArray())->toBe(['anthropic-beta' => 'files-api-2025-04-14,compact-2026-09-04,context-management-2025-06-27']);
});
