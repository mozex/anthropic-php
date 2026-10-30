<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources\Concerns;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\Testing\Requests\TestRequest;
use ReflectionMethod;
use ReflectionNamedType;

trait Testable
{
    public function __construct(protected ClientFake $fake) {}

    abstract protected function resource(): string;

    /**
     * Records the call with the type the method returns, so the fake can check its response fits, or make one.
     *
     * @param  array<string, mixed>  $args
     */
    protected function record(string $method, array $args = []): ResponseContract|ResponseStreamContract|RawResponse|string
    {
        // A label that isn't a method of the resource has no return type to check.
        $type = method_exists($this, $method) ? (new ReflectionMethod($this, $method))->getReturnType() : null;

        return $this->fake->record(new TestRequest($this->resource(), $method, $args), $type instanceof ReflectionNamedType ? $type->getName() : null);
    }

    public function assertSent(callable|int|null $callback = null): void
    {
        $this->fake->assertSent($this->resource(), $callback);
    }

    public function assertSentTimes(int $times, ?callable $callback = null): void
    {
        $this->fake->assertSentTimes($this->resource(), $times, $callback);
    }

    public function assertNotSent(callable|int|null $callback = null): void
    {
        $this->fake->assertNotSent($this->resource(), $callback);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function assertSentWithOptions(array $options): void
    {
        $this->fake->assertSentWithOptions($this->resource(), $options);
    }
}
