<?php

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\Transporters\HttpTransporter;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

beforeEach(function () {
    $this->client = Mockery::mock(ClientInterface::class);

    $apiKey = ApiKey::from('foo');

    $this->http = new HttpTransporter(
        $this->client,
        BaseUri::from('api.anthropic.com/v1'),
        Headers::withAuthorization($apiKey)->withContentType(ContentType::JSON),
        QueryParams::create()->withParam('foo', 'bar'),
        fn (RequestInterface $request): ResponseInterface => $this->client->sendAsyncRequest($request, ['stream' => true]),
    );
});

test('request object', function () {
    $payload = Payload::list('models');

    $response = new Response(200, ['Content-Type' => 'application/json; charset=utf-8', ...metaHeaders()], json_encode([
        'qdwq',
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->withArgs(function (Psr7Request $request) {
            expect($request->getMethod())->toBe('GET')
                ->and($request->getUri())
                ->getHost()->toBe('api.anthropic.com')
                ->getScheme()->toBe('https')
                ->getPath()->toBe('/v1/models');

            return true;
        })->andReturn($response);

    $this->http->requestObject($payload);
});

test('request object response', function () {
    $payload = Payload::list('models');

    $response = new Response(200, ['Content-Type' => 'application/json; charset=utf-8', ...metaHeaders()], json_encode([
        [
            'text' => 'Hey!',
            'index' => 0,
            'logprobs' => null,
            'finish_reason' => 'length',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    $response = $this->http->requestObject($payload);

    expect($response->data())->toBe([
        [
            'text' => 'Hey!',
            'index' => 0,
            'logprobs' => null,
            'finish_reason' => 'length',
        ],
    ]);
});

test('request object server user errors', function () {
    $payload = Payload::list('models');

    $response = new Response(401, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => 'Incorrect API key provided: foo.',
            'type' => 'invalid_request_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestObject($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorType())->toBe('invalid_request_error');
        });
});

test('request object server errors', function () {
    $payload = Payload::create('complete', ['model' => 'claude-2.1']);

    $response = new Response(401, ['Content-Type' => 'application/json'], json_encode([
        'error' => [
            'message' => 'That model is currently overloaded with other requests. You can ...',
            'type' => 'server_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestObject($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('That model is currently overloaded with other requests. You can ...')
                ->and($e->getErrorMessage())->toBe('That model is currently overloaded with other requests. You can ...')
                ->and($e->getErrorType())->toBe('server_error');
        });
});

test('error type may be null', function () {
    $payload = Payload::list('models');

    $response = new Response(429, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => 'You exceeded your current quota, please check',
            'type' => null,
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestObject($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('You exceeded your current quota, please check')
                ->and($e->getErrorMessage())->toBe('You exceeded your current quota, please check')
                ->and($e->getErrorType())->toBeNull();
        });
});

test('error message may be an array', function () {
    $payload = Payload::create('complete', ['model' => 'claude-2.1']);

    $response = new Response(404, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => [
                'Invalid schema for function \'get_current_weather\':',
                'In context=(\'properties\', \'location\'), array schema missing items',
            ],
            'type' => 'invalid_request_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestObject($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Invalid schema for function \'get_current_weather\':'.PHP_EOL.'In context=(\'properties\', \'location\'), array schema missing items')
                ->and($e->getErrorMessage())->toBe('Invalid schema for function \'get_current_weather\':'.PHP_EOL.'In context=(\'properties\', \'location\'), array schema missing items')
                ->and($e->getErrorType())->toBe('invalid_request_error');
        });
});

test('error message may be empty', function () {
    $payload = Payload::create('complete', ['model' => 'claude-2.1']);

    $response = new Response(404, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => '',
            'type' => 'invalid_request_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestObject($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Unknown error')
                ->and($e->getErrorMessage())->toBe('Unknown error')
                ->and($e->getErrorType())->toBe('invalid_request_error');
        });
});

test('request object client errors', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.anthropic.com');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andThrow(new ConnectException('Could not resolve host.', $payload->toRequest($baseUri, $headers, $queryParams)));

    expect(fn () => $this->http->requestObject($payload))->toThrow(function (TransporterException $e) {
        expect($e->getMessage())->toBe('Could not resolve host.')
            ->and($e->getCode())->toBe(0)
            ->and($e->getPrevious())->toBeInstanceOf(ConnectException::class);
    });
});

test('request object client error in response', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.anthropic.com');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andThrow(new \GuzzleHttp\Exception\ClientException(
            message: 'Could not resolve host.',
            request: $payload->toRequest($baseUri, $headers, $queryParams),
            response: new Response(401, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
                'error' => [
                    'message' => 'Incorrect API key provided: foo.',
                    'type' => 'invalid_request_error',
                ],
            ]))
        ));

    expect(fn () => $this->http->requestObject($payload))->toThrow(function (ErrorException $e) {
        expect($e->getMessage())
            ->toBe('Incorrect API key provided: foo.');
    });
});

test('request object serialization errors', function () {
    $payload = Payload::list('models');

    $response = new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], 'err');

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    $this->http->requestObject($payload);
})->throws(UnserializableResponse::class, 'Syntax error', 0);

test('request plain text', function () {
    $payload = Payload::upload('audio/transcriptions', []);

    $response = new Response(200, ['Content-Type' => 'text/plain; charset=utf-8', ...metaHeaders()], 'Hello, how are you?');

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    $response = $this->http->requestObject($payload);

    expect($response->data())->toBe('Hello, how are you?');
});

test('request content', function () {
    $payload = Payload::list('models');

    $response = new Response(200, [], json_encode([
        'qdwq',
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->withArgs(function (Psr7Request $request) {
            expect($request->getMethod())->toBe('GET')
                ->and($request->getUri())
                ->getHost()->toBe('api.anthropic.com')
                ->getScheme()->toBe('https')
                ->getPath()->toBe('/v1/models');

            return true;
        })->andReturn($response);

    $this->http->requestContent($payload);
});

test('request content response', function () {
    $payload = Payload::list('models');

    $response = new Response(200, [], 'My response content');

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    $response = $this->http->requestContent($payload);

    expect($response)->toBe('My response content');
});

test('request content client errors', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.anthropic.com');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andThrow(new ConnectException('Could not resolve host.', $payload->toRequest($baseUri, $headers, $queryParams)));

    expect(fn () => $this->http->requestContent($payload))->toThrow(function (TransporterException $e) {
        expect($e->getMessage())->toBe('Could not resolve host.')
            ->and($e->getCode())->toBe(0)
            ->and($e->getPrevious())->toBeInstanceOf(ConnectException::class);
    });
});

test('request content server errors', function () {
    $payload = Payload::list('models');

    $response = new Response(401, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => 'Incorrect API key provided: foo.',
            'type' => 'invalid_request_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestContent($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorType())->toBe('invalid_request_error');
        });
});

test('request stream', function () {
    $payload = Payload::create('complete', []);

    $response = new Response(200, [], json_encode([
        'qdwq',
    ]));

    $this->client
        ->shouldReceive('sendAsyncRequest')
        ->once()
        ->withArgs(function (Psr7Request $request) {
            expect($request->getMethod())->toBe('POST')
                ->and($request->getUri())
                ->getHost()->toBe('api.anthropic.com')
                ->getScheme()->toBe('https')
                ->getPath()->toBe('/v1/complete');

            return true;
        })->andReturn($response);

    $response = $this->http->requestStream($payload);

    expect($response->getBody()->eof())
        ->toBeFalse();
});

test('request stream server errors', function () {
    $payload = Payload::create('complete', []);

    $response = new Response(401, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
        'error' => [
            'message' => 'Incorrect API key provided: foo.',
            'type' => 'invalid_request_error',
        ],
    ]));

    $this->client
        ->shouldReceive('sendAsyncRequest')
        ->once()
        ->andReturn($response);

    expect(fn () => $this->http->requestStream($payload))
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorMessage())->toBe('Incorrect API key provided: foo.')
                ->and($e->getErrorType())->toBe('invalid_request_error');
        });
});
