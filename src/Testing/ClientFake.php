<?php

declare(strict_types=1);

namespace Anthropic\Testing;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Completions\CreateStreamedResponse as CompletionsCreateStreamedResponse;
use Anthropic\Responses\Completions\StreamResponse as CompletionsStreamResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse as MessagesStreamResponse;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\Requests\TestRequest;
use Anthropic\Testing\Resources\BatchesTestResource;
use Anthropic\Testing\Resources\CompletionsTestResource;
use Anthropic\Testing\Resources\FilesTestResource;
use Anthropic\Testing\Resources\MessagesTestResource;
use Anthropic\Testing\Resources\ModelsTestResource;
use Anthropic\Testing\Resources\RawTestResource;
use Anthropic\ValueObjects\Transporter\RequestOptions;
use Closure;
use Pest\Expectation;
use PHPUnit\Framework\Assert as PHPUnit;
use RuntimeException;
use Stringable;
use Throwable;

class ClientFake implements ClientContract
{
    /**
     * @var array<array-key, TestRequest>
     */
    private array $requests = [];

    /**
     * The options of a fake returned by withOptions(), recorded on each request it sends.
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * The fake a withOptions() fake or an onConnection() view came from: it holds the queue and the recorded requests
     * for all of them.
     */
    private ?ClientFake $root = null;

    /**
     * The connection an onConnection() view records its requests under. A subclass may name the root's, so calls
     * through the fake itself count as that connection's.
     */
    protected ?string $connection = null;

    /**
     * Answers checked before the queue, in the order added: a matcher, and the response or the closure that makes it.
     *
     * @var list<array{Closure(TestRequest): bool, mixed}>
     */
    private array $responders = [];

    /**
     * Whether a response was ever queued. Until one is, a call no responder answers gets its method's default fake.
     */
    private bool $queued = false;

    private bool $preventStrayRequests = false;

    private static bool $expectationsRegistered = false;

    /**
     * @param  array<array-key, ResponseContract|ResponseStreamContract|CompletionsStreamResponse|MessagesStreamResponse|RawResponse|Throwable|Stringable|string>  $responses
     */
    public function __construct(protected array $responses = [])
    {
        $this->queued = $responses !== [];

        self::registerPestExpectations();
    }

    /**
     * @param  array<array-key, ResponseContract|ResponseStreamContract|CompletionsStreamResponse|MessagesStreamResponse|RawResponse|Throwable|Stringable|string>  $responses
     */
    public function addResponses(array $responses): void
    {
        $root = $this->root();
        $root->responses = [...$root->responses, ...$responses];
        $root->queued = $root->queued || $responses !== [];
    }

    /**
     * Answers every matching call ahead of the queue, as often as it comes: a resource's class, a [resource, method]
     * pair or a closure taking the TestRequest. The response can be a closure taking the TestRequest, which a stream
     * needs, since one is read only once.
     *
     * @param  class-string|array{0: class-string, 1: string}|Closure(TestRequest): bool  $match
     */
    public function respondTo(string|array|Closure $match, mixed $response): static
    {
        $this->root()->responders[] = [self::matcher($match), $response];

        return $this;
    }

    /**
     * Makes a call that no queued response or responder answers throw, rather than get its method's default fake.
     */
    public function preventStrayRequests(bool $prevent = true): static
    {
        $this->root()->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * Returns a fake that shares this one's queue and recorded requests, and records these options on its requests.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        // Throws on the options the client would reject.
        RequestOptions::from($options);

        $fake = clone $this;
        $fake->root = $this->root();
        $fake->options = self::mergeOptions($this->options, self::normalizeOptions($options));

        return $fake;
    }

    /**
     * Returns a view of the fake that shares its queue and recorded requests, and records its requests under the
     * connection's name, for a framework that hands out one client per connection.
     */
    public function onConnection(string $name): static
    {
        $fake = clone $this;
        $fake->root = $this->root();
        $fake->connection = $name;

        return $fake;
    }

    /**
     * Asserts a request to the resource was sent through the named onConnection() view; with an int, exactly that
     * many times.
     */
    public function assertSentOn(string $connection, string $resource, callable|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $count = count($this->sentOn($connection, $resource));

            PHPUnit::assertSame($callback, $count, "The expected [{$resource}] resource was sent {$count} times on the [{$connection}] connection instead of {$callback} times.");

            return;
        }

        PHPUnit::assertNotEmpty($this->sentOn($connection, $resource, $callback), "The expected [{$resource}] request on the [{$connection}] connection was not sent.");
    }

    /**
     * Asserts no request to the resource (or none the callback accepts) was sent through the named onConnection() view.
     */
    public function assertNotSentOn(string $connection, string $resource, ?callable $callback = null): void
    {
        PHPUnit::assertCount(0, $this->sentOn($connection, $resource, $callback), "The unexpected [{$resource}] request on the [{$connection}] connection was sent.");
    }

    /**
     * @return array<array-key, TestRequest>
     */
    private function sentOn(string $connection, string $resource, ?callable $callback = null): array
    {
        $callback ??= fn (): bool => true;

        return array_filter(
            $this->resourcesOf($resource),
            fn (TestRequest $request): bool => $request->connection() === $connection && $callback($request->method(), ...$request->args()),
        );
    }

    /**
     * Asserts a request to the resource was sent by a withOptions() client carrying at least these options.
     *
     * @param  array<string, mixed>  $options
     */
    public function assertSentWithOptions(string $resource, array $options): void
    {
        if ($options === []) {
            throw new \InvalidArgumentException('Pass the options to look for.');
        }

        $options = self::normalizeOptions($options);

        $matching = array_filter(
            $this->resourcesOf($resource),
            fn (TestRequest $request): bool => self::containsOptions($request->options(), $options),
        );

        PHPUnit::assertNotEmpty($matching, "The expected [{$resource}] request with these options was not sent.");
    }

    public function assertSent(string $resource, callable|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertSentTimes($resource, $callback);

            return;
        }

        PHPUnit::assertTrue(
            $this->sent($resource, $callback) !== [],
            "The expected [{$resource}] request was not sent."
        );
    }

    /**
     * Asserts the resource was sent exactly that many times, counting only the calls the callback accepts with one.
     */
    public function assertSentTimes(string $resource, int $times, ?callable $callback = null): void
    {
        $count = count($this->sent($resource, $callback));

        PHPUnit::assertSame(
            $times, $count,
            "The expected [{$resource}] resource was sent {$count} times instead of {$times} times."
        );
    }

    /**
     * Asserts the calls were sent in this order, and no others: each a resource's class, a [resource, method] pair or
     * a closure taking the TestRequest.
     *
     * @param  list<class-string|array{0: class-string, 1: string}|Closure(TestRequest): bool>  $expected
     */
    public function assertSentInOrder(array $expected): void
    {
        $requests = array_values($this->root()->requests);
        $sent = 'The requests sent were: '.(implode(', ', array_map(self::describe(...), $requests)) ?: 'none').'.';

        PHPUnit::assertCount(count($expected), $requests, 'Expected '.count($expected).' requests, '.count($requests).' were sent. '.$sent);

        foreach (array_values($expected) as $index => $match) {
            PHPUnit::assertTrue(self::matcher($match)($requests[$index]), 'Request '.($index + 1).' was '.self::describe($requests[$index]).", not the one expected. {$sent}");
        }
    }

    /**
     * The recorded requests, in the order sent: those to the resource, those the callback accepts (with the method
     * and its arguments), or all of them.
     *
     * @return list<TestRequest>
     */
    public function sent(?string $resource = null, ?callable $callback = null): array
    {
        $callback ??= fn (): bool => true;
        $requests = $resource === null ? $this->root()->requests : $this->resourcesOf($resource);

        return array_values(array_filter($requests, fn (TestRequest $request): bool => (bool) $callback($request->method(), ...$request->args())));
    }

    /**
     * With an int, asserts the resource wasn't sent exactly that many times.
     */
    public function assertNotSent(string $resource, callable|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertNotSentTimes($resource, $callback);

            return;
        }

        PHPUnit::assertCount(
            0, $this->sent($resource, $callback),
            "The unexpected [{$resource}] request was sent."
        );
    }

    private function assertNotSentTimes(string $resource, int $times): void
    {
        $count = count($this->sent($resource));

        PHPUnit::assertNotSame(
            $times, $count,
            "The unexpected [{$resource}] request was sent {$count} times."
        );
    }

    public function assertNothingSent(): void
    {
        $requests = $this->root()->requests;

        $resourceNames = implode(
            separator: ', ',
            array: array_map(fn (TestRequest $request): string => $request->resource(), $requests)
        );

        PHPUnit::assertEmpty($requests, 'The following requests were sent unexpectedly: '.$resourceNames);
    }

    /**
     * @return array<array-key, TestRequest>
     */
    private function resourcesOf(string $type): array
    {
        return array_filter($this->root()->requests, fn (TestRequest $request): bool => $request->resource() === $type);
    }

    /**
     * Records the call and answers it: a responder's response, else the next queued one, else, while none was ever
     * queued and stray requests are allowed, the method's default fake. `$returns` is the type the method returns,
     * which a response must fit.
     */
    public function record(TestRequest $request, ?string $returns = null): ResponseContract|ResponseStreamContract|RawResponse|string
    {
        if ($this->options !== [] || $this->connection !== null) {
            $request = new TestRequest($request->resource(), $request->method(), $request->args(), $this->options, $this->connection);
        }

        $root = $this->root();
        $root->requests[] = $request;

        $response = $root->answer($request, $returns);

        if ($response instanceof Throwable) {
            throw $response;
        }

        // A stream queued for a content method such as files()->download().
        if ($response instanceof Stringable) {
            $response = (string) $response;
        }

        if ($returns !== null && ! ($returns === 'string' ? is_string($response) : $response instanceof $returns)) {
            throw new RuntimeException('The fake response for '.self::describe($request).' is '.get_debug_type($response).", but the method returns {$returns}.");
        }

        if (! $response instanceof ResponseContract && ! $response instanceof ResponseStreamContract && ! $response instanceof RawResponse && ! is_string($response)) {
            throw new RuntimeException('The fake response for '.self::describe($request).' is '.get_debug_type($response).', which no method returns.');
        }

        return $response;
    }

    /**
     * The answer to a recorded call, on the root fake.
     */
    private function answer(TestRequest $request, ?string $returns): mixed
    {
        foreach ($this->responders as [$matcher, $response]) {
            if ($matcher($request)) {
                return $response instanceof Closure ? $response($request) : $response;
            }
        }

        if ($this->responses !== []) {
            return array_shift($this->responses);
        }

        if (! $this->queued && ! $this->preventStrayRequests && $returns !== null) {
            return match ($returns) {
                'string' => '',
                // A stream's fake comes from the event it's made of.
                MessagesStreamResponse::class => CreateStreamedResponse::fake(),
                CompletionsStreamResponse::class => CompletionsCreateStreamedResponse::fake(),
                default => $returns::fake(),
            };
        }

        // The old message first, so an assertion on it still matches.
        throw new RuntimeException('No fake responses left. '.self::describe($request).match (true) {
            $this->queued => ' was the next call: queue another response, or answer it with respondTo().',
            $this->preventStrayRequests => ' has none, and stray requests are prevented: queue one, or answer it with respondTo().',
            default => ' has none, and its return type gives no default: queue one, or answer it with respondTo().',
        });
    }

    /**
     * @deprecated The Text Completions API is deprecated. Use messages() instead.
     */
    public function completions(): CompletionsTestResource
    {
        return new CompletionsTestResource($this);
    }

    public function messages(): MessagesTestResource
    {
        return new MessagesTestResource($this);
    }

    public function models(): ModelsTestResource
    {
        return new ModelsTestResource($this);
    }

    public function batches(): BatchesTestResource
    {
        return new BatchesTestResource($this);
    }

    public function files(): FilesTestResource
    {
        return new FilesTestResource($this);
    }

    /**
     * Records the request under `Raw::class` and answers it as any call: a responder, the queue, or the default fake.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function request(string $method, string $path, array $parameters = []): RawResponse
    {
        return (new RawTestResource($this))->request($method, $path, $parameters);
    }

    private function root(): ClientFake
    {
        return $this->root ?? $this;
    }

    /**
     * A matcher for a resource's class, a [resource, method] pair or a closure taking the TestRequest.
     *
     * @param  class-string|array{0: class-string, 1: string}|Closure(TestRequest): bool  $match
     * @return Closure(TestRequest): bool
     */
    private static function matcher(string|array|Closure $match): Closure
    {
        return match (true) {
            $match instanceof Closure => $match,
            is_array($match) => fn (TestRequest $request): bool => $request->resource() === $match[0] && $request->method() === $match[1],
            default => fn (TestRequest $request): bool => $request->resource() === $match,
        };
    }

    /**
     * A recorded call as a failure message names it: `Messages::create()`.
     */
    private static function describe(TestRequest $request): string
    {
        $resource = strrchr($request->resource(), '\\');

        return ($resource === false ? $request->resource() : substr($resource, 1)).'::'.$request->method().'()';
    }

    /**
     * Pest's `expect($fake)->toHaveSent(...)` and its siblings, registered the first time a fake is made under Pest:
     * on Pest's own Expectation, since another library may define `expect()`, and never over a suite's own expectation
     * of the same name.
     */
    private static function registerPestExpectations(): void
    {
        if (self::$expectationsRegistered || ! class_exists(Expectation::class)) {
            return;
        }

        self::$expectationsRegistered = true;

        $expectations = [
            'toHaveSent' => function (string $resource, callable|int|null $callback = null) {
                $this->value->assertSent($resource, $callback);

                return $this;
            },
            'toHaveSentTimes' => function (string $resource, int $times, ?callable $callback = null) {
                $this->value->assertSentTimes($resource, $times, $callback);

                return $this;
            },
            'toHaveSentOn' => function (string $connection, string $resource, callable|int|null $callback = null) {
                $this->value->assertSentOn($connection, $resource, $callback);

                return $this;
            },
            'toHaveSentInOrder' => function (array $expected) {
                $this->value->assertSentInOrder($expected);

                return $this;
            },
            'toHaveSentNothing' => function () {
                $this->value->assertNothingSent();

                return $this;
            },
        ];

        $expect = new Expectation(null);

        foreach ($expectations as $name => $expectation) {
            if (! Expectation::hasExtend($name)) {
                $expect->extend($name, $expectation);
            }
        }
    }

    /**
     * Trims betas and the string options as the client does, and lowercases header names, so assertions don't
     * depend on spelling.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function normalizeOptions(array $options): array
    {
        foreach ($options as $key => $value) {
            $options[$key] = match (true) {
                $key === 'betas' && is_array($value) => self::normalizeBetas($value),
                $key === 'headers' && is_array($value) => array_change_key_case($value, CASE_LOWER),
                is_string($value) => trim($value),
                default => $value,
            };
        }

        return $options;
    }

    /**
     * Trims the betas, drops blank ones and de-duplicates the rest.
     *
     * @param  array<array-key, mixed>  $betas
     * @return list<mixed>
     */
    private static function normalizeBetas(array $betas): array
    {
        $betas = array_map(fn (mixed $beta): mixed => is_string($beta) ? trim($beta) : $beta, $betas);

        return array_values(array_unique(array_filter($betas, fn (mixed $beta): bool => $beta !== '')));
    }

    /**
     * Later options win; headers and query merge by name, betas add up.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private static function mergeOptions(array $current, array $new): array
    {
        foreach ($new as $key => $value) {
            $current[$key] = match (true) {
                in_array($key, ['headers', 'query'], true) && is_array($value) && is_array($current[$key] ?? null) => [...$current[$key], ...$value],
                $key === 'betas' && is_array($value) && is_array($current[$key] ?? null) => array_values(array_unique([...$current[$key], ...$value])),
                default => $value,
            };
        }

        return $current;
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @param  array<array-key, mixed>  $expected
     */
    private static function containsOptions(array $options, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $options)) {
                return false;
            }

            $actual = $options[$key];

            $matches = match (true) {
                ! is_array($value) || ! is_array($actual) => $actual === $value,
                array_is_list($value) => array_diff($value, $actual) === [],
                default => self::containsOptions($actual, $value),
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
