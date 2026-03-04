<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

/**
 * Represents a single line in a batch results JSONL response.
 */
final class BatchIndividualResponse
{
    private function __construct(
        public readonly string $customId,
        public readonly BatchResult $result,
    ) {}

    /**
     * Acts as static factory, and returns a new BatchIndividualResponse instance.
     *
     * @param  array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string, error: array{type: string, message: string}}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['custom_id'],
            BatchResult::from($attributes['result']),
        );
    }

    /**
     * @return array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, message: string}}}
     */
    public function toArray(): array
    {
        return [
            'custom_id' => $this->customId,
            'result' => $this->result->toArray(),
        ];
    }
}
