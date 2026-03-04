<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

/**
 * Represents the error details for a failed batch result.
 */
final class BatchResultError
{
    /**
     * @param  string  $type  The inner error type (e.g., "invalid_request_error")
     * @param  string  $message  The error message
     */
    private function __construct(
        public readonly string $type,
        public readonly string $message,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchResultError instance.
     *
     * @param  array{type: string, message: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['message'],
        );
    }

    /**
     * @return array{type: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
        ];
    }
}
