<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

/**
 * Represents a single line in a batch results JSONL response.
 */
class BatchIndividualResponse
{
    /**
     * @param  array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string|null, error: array{type: string, message: string}}|array{type: string, message: string, request_id?: string|null}}}  $attributes
     */
    private function __construct(
        public readonly string $customId,
        public readonly BatchResult $result,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchIndividualResponse instance.
     *
     * @param  array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string|null, error: array{type: string, message: string}}|array{type: string, message: string, request_id?: string|null}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['custom_id'],
            BatchResult::from($attributes['result']),
            $attributes,
        );
    }

    /**
     * @return array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, message: string, request_id?: string|null}}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'custom_id' => $this->customId,
            'result' => $this->result->toArray(),
        ]);
    }
}
