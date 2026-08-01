<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseContentBlockStart
{
    /**
     * @param  array<int|string, mixed>|null  $input
     * @param  array<int|string, mixed>|null  $content
     */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $name,
        public readonly ?array $input,
        public readonly ?string $thinking,
        public readonly ?string $tool_use_id,
        public readonly ?array $content,
        public readonly ?CreateResponseContentFallbackModel $from,
        public readonly ?CreateResponseContentFallbackModel $to,
    ) {}

    /**
     * @param  array{id?: string, type?: string, text?: string, name?: string, input?: array<int|string, mixed>, thinking?: string, tool_use_id?: string, content?: array<int|string, mixed>, from?: array{model: string}, to?: array{model: string}}  $attributes
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
        );
    }

    /**
     * @return array{id: string|null, type: string|null, text: string|null, name: string|null, input: array<int|string, mixed>|null, thinking: string|null, tool_use_id?: string, content?: array<int|string, mixed>, from?: array{model: string}, to?: array{model: string}}
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

        return $result;
    }
}
