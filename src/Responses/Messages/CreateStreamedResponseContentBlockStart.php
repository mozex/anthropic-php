<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateStreamedResponseContentBlockStart
{
    /**
     * @param  array<int|string, mixed>|null  $input
     * @param  array<int|string, mixed>|string|null  $content
     * @param  array<int|string, mixed>|null  $citations
     * @param  array<int, array{type: string, tool: array<string, mixed>}>|null  $tool_changes
     * @param  array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null  $tools
     * @param  array{id?: string, type?: string, text?: string, name?: string, input?: array<int|string, mixed>, thinking?: string, tool_use_id?: string, content?: array<int|string, mixed>|string|null, from?: array{model: string}, to?: array{model: string}, signature?: string|null, data?: string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}  $attributes
     */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $name,
        public readonly ?array $input,
        public readonly ?string $thinking,
        public readonly ?string $tool_use_id,
        public readonly array|string|null $content,
        public readonly ?CreateResponseContentFallbackModel $from,
        public readonly ?CreateResponseContentFallbackModel $to,
        public readonly ?string $signature,
        public readonly ?string $data,
        public readonly ?array $citations,
        public readonly ?CreateResponseContentCaller $caller,
        public readonly ?string $file_id,
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
     * @param  array{id?: string, type?: string, text?: string, name?: string, input?: array<int|string, mixed>, thinking?: string, tool_use_id?: string, content?: array<int|string, mixed>|string|null, from?: array{model: string}, to?: array{model: string}, signature?: string|null, data?: string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'] ?? null,
            $attributes['type'] ?? null,
            $attributes['text'] ?? null,
            $attributes['name'] ?? null,
            $attributes['input'] ?? null,
            $attributes['thinking'] ?? null,
            $attributes['tool_use_id'] ?? null,
            $attributes['content'] ?? null,
            isset($attributes['from']) ? CreateResponseContentFallbackModel::from($attributes['from']) : null,
            isset($attributes['to']) ? CreateResponseContentFallbackModel::from($attributes['to']) : null,
            $attributes['signature'] ?? null,
            $attributes['data'] ?? null,
            $attributes['citations'] ?? null,
            isset($attributes['caller']) ? CreateResponseContentCaller::from($attributes['caller']) : null,
            $attributes['file_id'] ?? null,
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
     * @return array{id: string|null, type: string|null, text: string|null, name: string|null, input: array<int|string, mixed>|null, thinking: string|null, tool_use_id?: string, content?: array<int|string, mixed>|string|null, from?: array{model: string}, to?: array{model: string}, signature?: string|null, data?: string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, toolset_name?: string|null, server_name?: string|null, is_error?: bool|null, encrypted_content?: string|null, tool_changes?: array<int, array{type: string, tool: array<string, mixed>}>|null, mcp_server_name?: string|null, tools?: array<int, array{name: string, description?: string|null, input_schema: array<string, mixed>}>|null, trigger?: array{type: string, category?: string|null}|null}
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'text' => $this->text,
            'name' => $this->name,
            'input' => $this->input,
            'thinking' => $this->thinking,
        ];

        if ($this->tool_use_id !== null) {
            $result['tool_use_id'] = $this->tool_use_id;
        }

        if ($this->content !== null) {
            $result['content'] = $this->content;
        }

        if ($this->from !== null) {
            $result['from'] = $this->from->toArray();
        }

        if ($this->to !== null) {
            $result['to'] = $this->to->toArray();
        }

        if ($this->caller !== null) {
            $result['caller'] = $this->caller->toArray();
        }

        if ($this->trigger !== null) {
            $result['trigger'] = $this->trigger->toArray();
        }

        return array_replace($this->attributes, $result);
    }
}
