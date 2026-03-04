<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Meta\MetaInformation;

/**
 * Represents the result of a single request in a Message Batch.
 *
 * The type field determines which additional fields are present:
 * - "succeeded": message contains the full Message response
 * - "errored": error contains the error details
 * - "canceled": no additional fields
 * - "expired": no additional fields
 */
final class BatchResult
{
    private function __construct(
        public readonly string $type,
        public readonly ?CreateResponse $message,
        public readonly ?BatchResultError $error,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchResult instance.
     *
     * @param  array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string, error: array{type: string, message: string}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        $message = null;
        $error = null;

        if ($attributes['type'] === 'succeeded' && isset($attributes['message'])) {
            /** @var array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null}>, stop_reason: string} $messageData */
            $messageData = $attributes['message'];
            $message = CreateResponse::from($messageData, MetaInformation::from([]));
        }

        if ($attributes['type'] === 'errored' && isset($attributes['error'])) {
            $errorData = $attributes['error'];
            $error = BatchResultError::from($errorData['error']);
        }

        return new self(
            $attributes['type'],
            $message,
            $error,
        );
    }

    /**
     * @return array{type: string, message?: array<string, mixed>, error?: array{type: string, message: string}}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->message instanceof CreateResponse) {
            $result['message'] = $this->message->toArray();
        }

        if ($this->error instanceof BatchResultError) {
            $result['error'] = $this->error->toArray();
        }

        return $result;
    }
}
