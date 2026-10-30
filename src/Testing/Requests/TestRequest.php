<?php

declare(strict_types=1);

namespace Anthropic\Testing\Requests;

class TestRequest
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $options  the options of the withOptions() client that sent it
     */
    public function __construct(protected string $resource, protected string $method, protected array $args, protected array $options = []) {}

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
}
