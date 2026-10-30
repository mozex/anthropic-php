<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources;

use Anthropic\Resources\Raw;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\Resources\Concerns\Testable;
use Anthropic\ValueObjects\Transporter\Payload;

class RawTestResource
{
    use Testable;

    protected function resource(): string
    {
        return Raw::class;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function request(string $method, string $path, array $parameters = []): RawResponse
    {
        // The client's own checks first: a method or path it rejects throws before the request is recorded.
        Payload::raw($method, $path, $parameters);

        return $this->record(__FUNCTION__, func_get_args());
    }
}
