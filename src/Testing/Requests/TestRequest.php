<?php

declare(strict_types=1);

namespace Anthropic\Testing\Requests;

class TestRequest
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $options  the options of the withOptions() client that sent it
     * @param  string|null  $connection  the name of the onConnection() view that sent it, null for the fake itself
     */
    public function __construct(protected string $resource, protected string $method, protected array $args, protected array $options = [], protected ?string $connection = null) {}

    public function resource(): string
    {
        return $this->resource;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }
}
