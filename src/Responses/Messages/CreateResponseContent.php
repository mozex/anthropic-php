<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContent
{
    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<int|string, mixed>|null  $citations
     * @param  array<int|string, mixed>|string|null  $content
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text,
        public readonly ?string $id,
        public readonly ?string $name,
        public readonly ?array $input,
        public readonly ?string $thinking,
        public readonly ?string $signature,
        public readonly ?string $data,
        public readonly ?string $tool_use_id,
        public readonly array|string|null $content,
        public readonly ?array $citations,
        public readonly ?CreateResponseContentCaller $caller,
        public readonly ?string $file_id,
        public readonly ?CreateResponseContentFallbackModel $from,
        public readonly ?CreateResponseContentFallbackModel $to,
    ) {}

    /**
     * @param  array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}|null, to?: array{model: string}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['text'] ?? null,
            $attributes['id'] ?? null,
            $attributes['name'] ?? null,
            $attributes['input'] ?? null,
            $attributes['thinking'] ?? null,
            $attributes['signature'] ?? null,
            $attributes['data'] ?? null,
            $attributes['tool_use_id'] ?? null,
            $attributes['content'] ?? null,
            $attributes['citations'] ?? null,
            isset($attributes['caller']) ? CreateResponseContentCaller::from($attributes['caller']) : null,
            $attributes['file_id'] ?? null,
            isset($attributes['from']) ? CreateResponseContentFallbackModel::from($attributes['from']) : null,
            isset($attributes['to']) ? CreateResponseContentFallbackModel::from($attributes['to']) : null,
        );
    }

    /**
     * @return array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string}, file_id?: string|null, from?: array{model: string}, to?: array{model: string}}
     */
    public function toArray(): array
    {
        return match ($this->type) {
            'thinking' => [
                'type' => $this->type,
                'thinking' => $this->thinking,
                'signature' => $this->signature,
            ],
            'redacted_thinking' => [
                'type' => $this->type,
                'data' => $this->data,
            ],
            'tool_use', 'server_tool_use' => array_filter([
                'type' => $this->type,
                'id' => $this->id,
                'name' => $this->name,
                'input' => $this->input,
                'caller' => $this->caller?->toArray(),
            ], fn (mixed $value): bool => ! is_null($value)),
            'web_search_tool_result',
            'web_fetch_tool_result',
            'code_execution_tool_result',
            'bash_code_execution_tool_result',
            'text_editor_code_execution_tool_result',
            'tool_search_tool_result' => [
                'type' => $this->type,
                'tool_use_id' => $this->tool_use_id,
                'content' => $this->content,
            ],
            'container_upload' => [
                'type' => $this->type,
                'file_id' => $this->file_id,
            ],
            'compaction' => [
                'type' => $this->type,
                'content' => $this->content,
            ],
            'fallback' => array_filter([
                'type' => $this->type,
                'from' => $this->from?->toArray(),
                'to' => $this->to?->toArray(),
            ], fn (mixed $value): bool => ! is_null($value)),
            default => array_filter([
                'type' => $this->type,
                'text' => $this->text,
                'citations' => $this->citations,
            ], fn (mixed $value): bool => ! is_null($value)),
        };
    }
}
