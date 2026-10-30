<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources\Concerns;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\Testing\Requests\TestRequest;

trait Testable
{
    public function __construct(protected ClientFake $fake) {}

    abstract protected function resource(): string;

    /**
     * @param  array<string, mixed>  $args
     */
    protected function record(string $method, array $args = []): ResponseContract|ResponseStreamContract|RawResponse|string
    {
        return $this->fake->record(new TestRequest($this->resource(), $method, $args));
    }

    public function assertSent(callable|int|null $callback = null): void
    {
        $this->fake->assertSent($this->resource(), $callback);
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
