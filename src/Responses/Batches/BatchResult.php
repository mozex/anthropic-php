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
class BatchResult
{
    /**
     * @param  array{type: string, message?: array<string, mixed>, error?: array{type: string, message: string, request_id?: string|null}}  $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly ?CreateResponse $message,
        public readonly ?BatchResultError $error,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchResult instance.
     *
     * @param  array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string|null, error: array{type: string, message: string}}|array{type: string, message: string, request_id?: string|null}}  $attributes
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

        $payload = $attributes;

        if ($attributes['type'] === 'errored' && isset($attributes['error'])) {
            $error = BatchResultError::from(self::flattenError($attributes['error']));

            // kept once, flattened, in its place
            $payload['error'] = $error->toArray();
        } else {
            unset($payload['error']);
        }

        return new self(
            $attributes['type'],
            $message,
            $error,
            $payload,
        );
    }

    /**
     * @return array{type: string, message?: array<string, mixed>, error?: array{type: string, message: string, request_id?: string|null}}
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

        return array_replace($this->attributes, $result);
    }

    /**
     * The API wraps an errored result's error (`{type: "error", error: {type, message}, request_id}`), while
     * toArray() gives it flattened, with the wrapper's other keys (`request_id`) beside the type and message,
     * so both build the same error. The wrapper's own `type` is always "error".
     *
     * @param  array{type: string, request_id?: string|null, error: array{type: string, message: string}}|array{type: string, message: string, request_id?: string|null}  $error
     * @return array{type: string, message: string, request_id?: string|null}
     */
    protected static function flattenError(array $error): array
    {
        if (! isset($error['error'])) {
            return $error;
        }

        $inner = $error['error'];
        unset($error['type'], $error['error']);

        return [...$inner, ...$error];
    }
}
