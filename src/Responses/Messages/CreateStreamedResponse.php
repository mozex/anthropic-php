<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Messages\FakeableForStreamedResponse;

/**
 * @implements ResponseContract<array{type: string, index: int|null, delta: array{type: string|null, text: string|null, partial_json?: string|null, stop_reason: string|null, stop_sequence: string|null, thinking?: string|null, signature?: string|null}, message: array{id: string|null, type: string|null, role: string|null, content: array<int, string>|null, model: string|null, stop_reason: string|null, stop_sequence: string|null}, content_block_start: array{id: string|null, type: string|null, text: string|null, name: string|null, input: array<int|string, mixed>|null, thinking: string|null, tool_use_id?: string, content?: array<int|string, mixed>}, usage: array{input_tokens: int|null, output_tokens: int|null, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>}}>
 */
final class CreateStreamedResponse implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string, index: int|null, delta: array{type: string|null, text: string|null, partial_json?: string|null, stop_reason: string|null, stop_sequence: string|null, thinking?: string|null, signature?: string|null}, message: array{id: string|null, type: string|null, role: string|null, content: array<int, string>|null, model: string|null, stop_reason: string|null, stop_sequence: string|null}, content_block_start: array{id: string|null, type: string|null, text: string|null, name: string|null, input: array<int|string, mixed>|null, thinking: string|null, tool_use_id?: string, content?: array<int|string, mixed>}, usage: array{input_tokens: int|null, output_tokens: int|null, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>}}>
     */
    use ArrayAccessible;

    use FakeableForStreamedResponse;

    private function __construct(
        public readonly string $type,
        public readonly ?int $index,
        public readonly CreateStreamedResponseDelta $delta,
        public readonly CreateStreamedResponseMessage $message,
        public readonly CreateStreamedResponseContentBlockStart $content_block_start,
        public readonly CreateStreamedResponseUsage $usage,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: string, index?: int, delta?: array{type?: string, text?: string|null, partial_json?: string|null, stop_reason?: string, stop_sequence?: string|null, thinking?: string|null, signature?: string|null}, usage?: array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}}, message?: array{id?: string, type?: string, role?: string, content?: array<int, string>, model?: string, stop_reason?: string|null, stop_sequence?: string|null, usage?: array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}}, content_block?: array{id?: string, type?: string, text?: string, name?: string, input?: array<int|string, mixed>, thinking?: string, tool_use_id?: string, content?: array<int|string, mixed>}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['index'] ?? null,
            CreateStreamedResponseDelta::from($attributes['delta'] ?? []),
            CreateStreamedResponseMessage::from($attributes['message'] ?? []),
            CreateStreamedResponseContentBlockStart::from($attributes['content_block'] ?? []),
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
            'content_block_start' => $this->content_block_start->toArray(),
            'usage' => $this->usage->toArray(),
        ];
    }
}
