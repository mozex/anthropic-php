<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Messages\FakeableForStreamedResponse;

/**
 * @implements ResponseContract<array{type: string, index: int|null, delta: array{type: string|null, text: string|null, stop_reason: string|null, stop_sequence: string|null}, message: array{id: string|null, type: string|null, role: string|null, content: array<int, string>|null, model: string|null, stop_reason: string|null, stop_sequence:string|null}, usage: array{input_tokens: int|null, output_tokens: int|null}}>
 */
final class CreateStreamedResponse implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string, index: int|null, delta: array{type: string|null, text: string|null, stop_reason: string|null, stop_sequence: string|null}, message: array{id: string|null, type: string|null, role: string|null, content: array<int, string>|null, model: string|null, stop_reason: string|null, stop_sequence:string|null}, usage: array{input_tokens: int|null, output_tokens: int|null}}>
     */
    use ArrayAccessible;

    use FakeableForStreamedResponse;

    private function __construct(
        public readonly string $type,
        public readonly ?int $index,
        public readonly CreateStreamedResponseDelta $delta,
        public readonly CreateStreamedResponseMessage $message,
        public readonly CreateStreamedResponseUsage $usage,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: string, index?: int, delta?: array{type: string, text: string|null, stop_reason?: string, stop_sequence?:string|null}, usage?: array{output_tokens: int}, message?: array{id: string, type: string, role: string, content: array<int, string>, model: string, stop_reason: string|null, stop_sequence:string|null, usage?: array{input_tokens: int, output_tokens: int}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['index'] ?? null,
            CreateStreamedResponseDelta::from($attributes['delta'] ?? []),
            CreateStreamedResponseMessage::from($attributes['message'] ?? []),
            CreateStreamedResponseUsage::from($attributes['usage'] ?? $attributes['message']['usage'] ?? []),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'index' => $this->index,
            'delta' => $this->delta->toArray(),
            'message' => $this->message->toArray(),
            'usage' => $this->usage->toArray(),
        ];
    }
}
