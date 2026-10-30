<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use ArrayObject;

class CreateResponseContent
{
    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<int|string, mixed>|null  $citations
     * @param  array<int|string, mixed>|string|null  $content
     * @param  array<int, array{type: string, tool: array<string, mixed>}>|null  $tool_changes
     * @param  array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null  $tools
     * @param  array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|ArrayObject<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}|null, to?: array{model: string}|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}  $attributes
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
        public readonly ?string $toolset_name,
        public readonly ?string $server_name,
        public readonly ?bool $is_error,
        public readonly ?string $encrypted_content,
        public readonly ?array $tool_changes,
        public readonly ?string $mcp_server_name,
        public readonly ?array $tools,
        public readonly ?CreateResponseContentFallbackTrigger $trigger,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|ArrayObject<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}|null, to?: array{model: string}|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        $input = $attributes['input'] ?? null;

        // toArray() hands out an empty input as an ArrayObject
        if ($input instanceof ArrayObject) {
            $input = $input->getArrayCopy();
        }

        return new self(
            $attributes['type'],
            $attributes['text'] ?? null,
            $attributes['id'] ?? null,
            $attributes['name'] ?? null,
            $input,
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
            $attributes['toolset_name'] ?? null,
            $attributes['server_name'] ?? null,
            $attributes['is_error'] ?? null,
            $attributes['encrypted_content'] ?? null,
            $attributes['tool_changes'] ?? null,
            $attributes['mcp_server_name'] ?? null,
            $attributes['tools'] ?? null,
            isset($attributes['trigger']) ? CreateResponseContentFallbackTrigger::from($attributes['trigger']) : null,
            $attributes,
        );
    }

    /**
     * Returns the block as the API sent it, with the known fields re-serialized, so it can be sent back unchanged.
     *
     * An empty tool `input` comes back as an `ArrayObject`, which JSON-encodes as the object the API requires.
     *
     * @return array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|ArrayObject<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}|null, to?: array{model: string}|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, match ($this->type) {
            'text' => array_filter([
                'type' => $this->type,
                'text' => $this->text,
                'citations' => $this->citations,
            ], fn (mixed $value): bool => ! is_null($value)),
            'thinking' => [
                'type' => $this->type,
                'thinking' => $this->thinking,
                'signature' => $this->signature,
            ],
            'redacted_thinking' => [
                'type' => $this->type,
                'data' => $this->data,
            ],
            'tool_use', 'server_tool_use', 'mcp_tool_use' => array_filter([
                'type' => $this->type,
                'id' => $this->id,
                'name' => $this->name,
                'input' => $this->input === [] ? new ArrayObject : $this->input,
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
                ...($this->caller instanceof CreateResponseContentCaller ? ['caller' => $this->caller->toArray()] : []),
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
                'trigger' => $this->trigger?->toArray(),
            ], fn (mixed $value): bool => ! is_null($value)),
            default => [
                'type' => $this->type,
            ],
        });
    }
}
