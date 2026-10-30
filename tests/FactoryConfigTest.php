<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Factory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends requests through a client built from the config, answered in turn by the responses, and returns what went
 * out.
 *
 * @param  array<string, mixed>  $config
 * @param  list<ResponseInterface>|null  $responses
 * @return list<RequestInterface>
 */
function configuredRequests(array $config, ?Factory $factory = null, ?array $responses = null): array
{
    $history = [];
    $stack = HandlerStack::create(new MockHandler($responses ?? [new Response(200, ['Content-Type' => 'application/json'], json_encode(modelRetrieve()))]));
    $stack->push(Middleware::history($history));

    ($factory ?? Anthropic::factory())->withConfig([...$config, 'http_client' => new GuzzleClient(['handler' => $stack])])->withRetrySleep(fn (int $milliseconds) => null)->make()->models()->retrieve('claude-sonnet-4-6');

    return array_map(fn (array $entry): RequestInterface => $entry['request'], $history);
}

/**
 * @param  array<string, mixed>  $config
 */
function configuredRequest(array $config, ?Factory $factory = null): RequestInterface
{
    return configuredRequests($config, $factory)[0];
}

test('the config sets the key, workspace, version and headers, the headers applied last', function () {
    $request = configuredRequest([
        'api_key' => 'foo',
        'workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy',
        'api_version' => '2026-10-21',
        'betas' => ['compact-2026-09-04'],
        'headers' => ['X-Request-Source' => 'billing-report', 'X-Retries' => 2, 'X-Skip-Cache' => true, 'anthropic-beta' => 'files-api-2025-04-14', 'X-Blank' => ' ', 'X-Unset' => null],
    ]);

    expect($request->getHeaderLine('x-api-key'))->toBe('foo')
        ->and($request->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->and($request->getHeaderLine('anthropic-version'))->toBe('2026-10-21')
        ->and($request->getHeaderLine('X-Request-Source'))->toBe('billing-report')
        ->and($request->getHeaderLine('X-Retries'))->toBe('2')
        ->and($request->getHeaderLine('X-Skip-Cache'))->toBe('true')
        // the headers key wins over the betas key
        ->and($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14')
        ->and($request->hasHeader('X-Blank'))->toBeFalse()
        ->and($request->hasHeader('X-Unset'))->toBeFalse();
});

test('the betas go out as one anthropic-beta header, from a list or a comma-separated string', function (array|string $betas) {
    expect(configuredRequest(['api_key' => 'foo', 'betas' => $betas])->getHeaderLine('anthropic-beta'))->toBe('compact-2026-09-04,skills-2025-10-02');
})->with([
    'a list' => [['compact-2026-09-04', ' ', 'skills-2025-10-02', 'compact-2026-09-04']],
    'a string' => ['compact-2026-09-04, skills-2025-10-02,'],
]);

test('one credential: the key when one is set, the token otherwise', function (array $config, ?string $key, ?string $bearer) {
    $request = configuredRequest($config);

    expect($request->hasHeader('x-api-key'))->toBe($key !== null)
        ->and($request->getHeaderLine('x-api-key'))->toBe($key ?? '')
        ->and($request->getHeaderLine('Authorization'))->toBe($bearer === null ? '' : 'Bearer '.$bearer);
})->with([
    'a key' => [['api_key' => 'foo', 'auth_token' => 'sk-ant-oat01-token'], 'foo', null],
    'a token' => [['auth_token' => 'sk-ant-oat01-token'], null, 'sk-ant-oat01-token'],
    'a blank key beside a token' => [['api_key' => ' ', 'auth_token' => 'sk-ant-oat01-token'], null, 'sk-ant-oat01-token'],
    'a blank key alone' => [['api_key' => ' '], null, null],
]);

test('the config\'s credential replaces the one set before it', function () {
    $keyed = configuredRequest(['api_key' => 'foo'], Anthropic::factory()->withAuthToken('sk-ant-oat01-token'));
    $tokened = configuredRequest(['auth_token' => 'sk-ant-oat01-token'], Anthropic::factory()->withApiKey('foo'));
    $kept = configuredRequest(['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy'], Anthropic::factory()->withApiKey('foo'));

    expect($keyed->getHeaderLine('x-api-key'))->toBe('foo')
        ->and($keyed->hasHeader('Authorization'))->toBeFalse()
        ->and($tokened->getHeaderLine('Authorization'))->toBe('Bearer sk-ant-oat01-token')
        ->and($tokened->hasHeader('x-api-key'))->toBeFalse()
        ->and($kept->getHeaderLine('x-api-key'))->toBe('foo');
});

test('null and blank values keep the defaults', function () {
    $request = configuredRequest(['api_key' => 'foo', 'workspace_id' => '', 'api_version' => ' ', 'base_uri' => ' ', 'base_url' => null, 'betas' => [], 'max_retries' => '', 'headers' => null, 'middleware' => null]);

    expect($request->hasHeader('anthropic-workspace-id'))->toBeFalse()
        ->and($request->hasHeader('anthropic-beta'))->toBeFalse()
        ->and($request->getHeaderLine('anthropic-version'))->toBe('2023-06-01')
        ->and((string) $request->getUri())->toBe('https://api.anthropic.com/v1/models/claude-sonnet-4-6');
});

test('a base url takes the official SDKs\' meaning, a base uri the full root', function () {
    expect((string) configuredRequest(['api_key' => 'foo', 'base_url' => 'https://openrouter.ai/api'])->getUri())->toBe('https://openrouter.ai/api/v1/models/claude-sonnet-4-6')
        ->and((string) configuredRequest(['api_key' => 'foo', 'base_uri' => 'https://api.anthropic.com/v1'])->getUri())->toBe('https://api.anthropic.com/v1/models/claude-sonnet-4-6');
});

test('the config\'s retries send a failed request again, through its middleware each time', function () {
    $seen = new ArrayObject;
    $requests = configuredRequests(['api_key' => 'foo', 'max_retries' => '1', 'middleware' => function (RequestInterface $request, Closure $next) use ($seen): ResponseInterface {
        $seen->append(true);

        return $next($request->withHeader('X-Tagged', 'yes'));
    }], responses: [
        new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode(modelRetrieve())),
    ]);

    expect($seen)->toHaveCount(2)
        ->and($requests)->toHaveCount(2)
        ->and($requests[1]->getHeaderLine('X-Tagged'))->toBe('yes');
});

test('a max_retries of 0 turns off the retries an earlier call set', function () {
    $client = Anthropic::factory()->withMaxRetries(3)->withConfig(['api_key' => 'foo', 'max_retries' => 0])->make();
    $transporter = (new ReflectionProperty($client, 'transporter'))->getValue($client);

    expect((new ReflectionProperty($transporter, 'maxRetries'))->getValue($transporter))->toBe(0);
});

test('the timeouts build a Guzzle client, and throw beside one of yours', function () {
    $client = Anthropic::factory()->withConfig(['api_key' => 'foo', 'timeout' => '120', 'connect_timeout' => 5])->make();
    $transporter = (new ReflectionProperty($client, 'transporter'))->getValue($client);
    $guzzle = (new ReflectionProperty($transporter, 'client'))->getValue($transporter);

    expect($guzzle->getConfig('timeout'))->toBe(120.0)
        ->and($guzzle->getConfig('connect_timeout'))->toBe(5.0)
        ->and(fn () => Anthropic::factory()->withConfig(['api_key' => 'foo', 'timeout' => 30, 'http_client' => new GuzzleClient])->make())->toThrow(InvalidArgumentException::class)
        // a timeout of 0 keeps your client's own
        ->and(Anthropic::factory()->withConfig(['api_key' => 'foo', 'timeout' => 0, 'http_client' => new GuzzleClient])->make())->toBeInstanceOf(Client::class);
});

test('an unknown key and a value of the wrong type throw', function (array $config, string $message) {
    expect(fn () => Anthropic::factory()->withConfig($config))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'an unknown key' => [['api_key' => 'foo', 'request_timeout' => 30], 'Unknown config key [request_timeout]'],
    'a key that isn\'t a string' => [['api_key' => 123], 'The [api_key] config value must be a string.'],
    'both base keys' => [['base_uri' => 'https://api.anthropic.com/v1', 'base_url' => 'https://api.anthropic.com'], 'not both'],
    'betas that aren\'t a list' => [['betas' => 5], 'The [betas] config value'],
    'a beta that isn\'t a string' => [['betas' => ['compact-2026-09-04', 5]], 'Each beta'],
    'a timeout that isn\'t a number' => [['timeout' => 'soon'], 'The [timeout] config value'],
    'a negative connect timeout' => [['connect_timeout' => -1], 'The [connect_timeout] config value'],
    'a fractional retry count' => [['max_retries' => '2.5'], 'The [max_retries] config value'],
    'a negative retry count' => [['max_retries' => -1], 'The [max_retries] config value'],
    'middleware that isn\'t callable' => [['middleware' => ['not callable']], 'Each middleware must be callable'],
    'middleware that isn\'t a list' => [['middleware' => 5], 'The [middleware] config value'],
    'headers that aren\'t an array' => [['headers' => 'X-Request-Source: billing-report'], 'The [headers] config value'],
    'a header that isn\'t a string' => [['headers' => ['X-Request-Source' => ['billing-report']]], 'Each header'],
    'an http client' => [['http_client' => new stdClass], 'PSR-18'],
    'a timeout beside an http client' => [['timeout' => 30, 'http_client' => new GuzzleClient], 'set them on your [http_client]'],
]);

test('getenv() of an unset variable and empty lines keep the defaults', function () {
    $request = configuredRequest(['api_key' => 'foo', 'workspace_id' => false, 'headers' => '', 'middleware' => ' ', 'betas' => false]);

    expect($request->hasHeader('anthropic-workspace-id'))->toBeFalse()
        ->and($request->hasHeader('anthropic-beta'))->toBeFalse();
});

test('a config that throws applies none of its values', function () {
    $factory = Anthropic::factory()->withApiKey('foo');

    expect(fn () => $factory->withConfig(['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy', 'middleware' => ['not callable']]))->toThrow(InvalidArgumentException::class);

    expect(configuredRequest([], $factory)->hasHeader('anthropic-workspace-id'))->toBeFalse();
});
