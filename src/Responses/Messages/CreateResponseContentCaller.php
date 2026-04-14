<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContentCaller
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $tool_id,
    ) {}

    /**
     * @param  array{type: string, tool_id?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['tool_id'] ?? null,
        );
    }

    /**
     * @return array{type: string, tool_id?: string}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->tool_id !== null) {
            $result['tool_id'] = $this->tool_id;
        }

        return $result;
    }
}
