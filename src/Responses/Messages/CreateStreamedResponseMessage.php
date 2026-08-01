<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseMessage
{
    /**
     * @param  array<int, string>  $content
     */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $role,
        public readonly ?array $content,
        public readonly ?string $model,
        public readonly ?string $stop_reason,
        public readonly ?string $stop_sequence,
        public readonly ?CreateResponseDiagnostics $diagnostics,
    ) {}

    /**
     * @param  array{id?: string, type?: string, role?: string, content?: array<int, string>, model?: string, stop_reason?: string|null, stop_sequence?:string|null, diagnostics?: array{cache_miss_reason?: array{type: string, cache_missed_input_tokens?: int|null}|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'] ?? null,
            $attributes['type'] ?? null,
            $attributes['role'] ?? null,
            $attributes['content'] ?? null,
            $attributes['model'] ?? null,
            $attributes['stop_reason'] ?? null,
            $attributes['stop_sequence'] ?? null,
            isset($attributes['diagnostics']) ? CreateResponseDiagnostics::from($attributes['diagnostics']) : null,
        );
    }

    /**
     * @return array{id: string|null, type: string|null, role: string|null, content: array<int, string>|null, model: string|null, stop_reason: string|null, stop_sequence:string|null, diagnostics?: array{cache_miss_reason: array{type: string, cache_missed_input_tokens?: int}|null}}
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'role' => $this->role,
            'content' => $this->content,
            'model' => $this->model,
            'stop_reason' => $this->stop_reason,
            'stop_sequence' => $this->stop_sequence,
        ];

        if ($this->diagnostics !== null) {
            $result['diagnostics'] = $this->diagnostics->toArray();
        }

        return $result;
    }
}
