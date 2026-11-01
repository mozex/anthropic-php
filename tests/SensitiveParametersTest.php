<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Factory;

/**
 * Whether the value, or any array inside it, holds the text.
 */
function traceHolds(mixed $value, string $text): bool
{
    if (is_string($value)) {
        return str_contains($value, $text);
    }

    return is_array($value) && array_filter($value, fn (mixed $item): bool => traceHolds($item, $text)) !== [];
}

/**
 * The frames of what the call throws, from the named method in: the ones above it are the caller's, this test's
 * closure among them. Arguments are kept for the call, as error trackers keep them.
 *
 * @return list<array<string, mixed>>
 */
function framesFrom(string $method, Closure $call): array
{
    $ignoreArgs = (string) ini_get('zend.exception_ignore_args');

    // php.ini-production leaves the arguments out, but Flare turns that off at runtime to collect them.
    ini_set('zend.exception_ignore_args', '0');

    try {
        $call();
    } catch (InvalidArgumentException $exception) {
    } finally {
        ini_set('zend.exception_ignore_args', $ignoreArgs);
    }

    $trace = $exception->getTrace();
    $index = array_search($method, array_column($trace, 'function'), true);

    expect($index)->toBeInt();

    return array_slice($trace, 0, (int) $index + 1);
}

test('a config error keeps the credentials out of the trace', function (array $config) {
    $frames = framesFrom('withConfig', fn () => Anthropic::factory()->withConfig($config));

    expect(end($frames)['args'][0])->toBeInstanceOf(SensitiveParameterValue::class)
        ->and(traceHolds(array_column($frames, 'args'), 'sk-ant-api03-trace'))->toBeFalse();
})->with([
    'a timeout that isn\'t a number' => [['api_key' => 'sk-ant-api03-trace', 'timeout' => 'soon']],
    'a key that isn\'t a string' => [['api_key' => ['sk-ant-api03-trace']]],
    'a header that isn\'t a string' => [['headers' => ['x-api-key' => 'sk-ant-api03-trace', 'X-Retries' => ['2']]]],
]);

test('a request options error keeps the credentials out of the trace', function (array $options) {
    $frames = framesFrom('withOptions', fn () => Anthropic::client('foo')->withOptions($options));

    expect(end($frames)['args'][0])->toBeInstanceOf(SensitiveParameterValue::class)
        ->and(traceHolds(array_column($frames, 'args'), 'sk-ant-oat01-trace'))->toBeFalse();
})->with([
    'a retry count that isn\'t a number' => [['headers' => ['Authorization' => 'Bearer sk-ant-oat01-trace'], 'max_retries' => 'many']],
    'headers that aren\'t an array' => [['headers' => 'Authorization: Bearer sk-ant-oat01-trace']],
    'a header that isn\'t a string' => [['headers' => ['Authorization' => ['Bearer sk-ant-oat01-trace']]]],
]);

test('the methods that take a credential keep it out of a trace', function (string $class, string $method) {
    expect((new ReflectionParameter([$class, $method], 0))->getAttributes(SensitiveParameter::class))->not->toBeEmpty();
})->with([
    [Anthropic::class, 'client'],
    [Factory::class, 'withApiKey'],
    [Factory::class, 'withAuthToken'],
    [Factory::class, 'withConfig'],
    [Client::class, 'withOptions'],
]);
