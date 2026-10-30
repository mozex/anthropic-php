<?php

declare(strict_types=1);

namespace Anthropic\Testing;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Completions\StreamResponse as CompletionsStreamResponse;
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
use PHPUnit\Framework\Assert as PHPUnit;
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
     * @param  array<array-key, ResponseContract|ResponseStreamContract|CompletionsStreamResponse|MessagesStreamResponse|RawResponse|Throwable|Stringable|string>  $responses
     */
    public function __construct(protected array $responses = []) {}

    /**
     * @param  array<array-key, ResponseContract|ResponseStreamContract|CompletionsStreamResponse|MessagesStreamResponse|RawResponse|Throwable|Stringable|string>  $responses
     */
    public function addResponses(array $responses): void
    {
        $root = $this->root();
        $root->responses = [...$root->responses, ...$responses];
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

    private function assertSentTimes(string $resource, int $times = 1): void
    {
        $count = count($this->sent($resource));

        PHPUnit::assertSame(
            $times, $count,
            "The expected [{$resource}] resource was sent {$count} times instead of {$times} times."
        );
    }

    /**
     * @return mixed[]
     */
    private function sent(string $resource, ?callable $callback = null): array
    {
        if (! $this->hasSent($resource)) {
            return [];
        }

        $callback = $callback ?: fn (): bool => true;

        return array_filter($this->resourcesOf($resource), fn (TestRequest $resource) => $callback($resource->method(), ...$resource->args()));
    }

    private function hasSent(string $resource): bool
    {
        return $this->resourcesOf($resource) !== [];
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

    public function record(TestRequest $request): ResponseContract|ResponseStreamContract|RawResponse|string
    {
        if ($this->options !== [] || $this->connection !== null) {
            $request = new TestRequest($request->resource(), $request->method(), $request->args(), $this->options, $this->connection);
        }

        $root = $this->root();
        $root->requests[] = $request;

        $response = array_shift($root->responses);

        if (is_null($response)) {
            throw new \Exception('No fake responses left.');
        }

        if ($response instanceof Throwable) {
            throw $response;
        }

        // A stream queued for a content method such as files()->download().
        if ($response instanceof Stringable) {
            return (string) $response;
        }

        return $response;
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
     * Records the request under `Raw::class` and returns the next queued response, a `RawResponse::fake()`.
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
