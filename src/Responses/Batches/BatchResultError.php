<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

/**
 * Represents the error details for a failed batch result.
 */
class BatchResultError
{
    /**
     * @param  string  $type  The inner error type (e.g., "invalid_request_error")
     * @param  string  $message  The error message
     * @param  string|null  $requestId  The ID of the failed request, from the API's error wrapper
     * @param  array{type: string, message: string, request_id?: string|null}  $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly ?string $requestId,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchResultError instance.
     *
     * @param  array{type: string, message: string, request_id?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['message'],
            $attributes['request_id'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{type: string, message: string, request_id?: string|null}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'type' => $this->type,
            'message' => $this->message,
        ]);
    }
}
