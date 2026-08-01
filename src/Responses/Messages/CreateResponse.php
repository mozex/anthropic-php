<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Messages\Fakeable;

/**
 * @implements ResponseContract<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>, inference_geo?: string, output_tokens_details?: array{thinking_tokens: int}, speed?: string, iterations?: array<int, array{type: string, model?: string, input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_creation_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}}>}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}, to?: array{model: string}}>, stop_reason: string, stop_details?: array{type: string, category: string|null, explanation: string|null, recommended_model?: string}, container?: array{id: string, expires_at: string}, context_management?: array{applied_edits: array<int, array<string, mixed>>}, diagnostics?: array{cache_miss_reason: array{type: string, cache_missed_input_tokens?: int}|null}}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>, inference_geo?: string, output_tokens_details?: array{thinking_tokens: int}, speed?: string, iterations?: array<int, array{type: string, model?: string, input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_creation_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}}>}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}, to?: array{model: string}}>, stop_reason: string, stop_details?: array{type: string, category: string|null, explanation: string|null, recommended_model?: string}, container?: array{id: string, expires_at: string}, context_management?: array{applied_edits: array<int, array<string, mixed>>}, diagnostics?: array{cache_miss_reason: array{type: string, cache_missed_input_tokens?: int}|null}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, CreateResponseContent>  $content
     * @param  array{id: string, expires_at: string}|null  $container
     * @param  array{applied_edits: array<int, array<string, mixed>>}|null  $context_management
     */
    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $role,
        public readonly string $model,
        public readonly ?string $stop_sequence,
        public readonly string $stop_reason,
        public readonly ?CreateResponseStopDetails $stop_details,
        public readonly array $content,
        public readonly CreateResponseUsage $usage,
        public readonly ?array $container,
        public readonly ?array $context_management,
        public readonly ?CreateResponseDiagnostics $diagnostics,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int|null, cache_read_input_tokens?: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}|null, inference_geo?: string|null, output_tokens_details?: array{thinking_tokens?: int}|null, speed?: string|null, iterations?: array<int, array{type: string, model?: string|null, input_tokens: int, output_tokens: int, cache_read_input_tokens?: int, cache_creation_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null}>|null}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|string|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null, from?: array{model: string}|null, to?: array{model: string}|null}>, stop_reason: string, stop_details?: array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null}|null, container?: array{id: string, expires_at: string}|null, context_management?: array{applied_edits: array<int, array<string, mixed>>}|null, diagnostics?: array{cache_miss_reason?: array{type: string, cache_missed_input_tokens?: int|null}|null}|null}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $content = array_map(fn (array $result): CreateResponseContent => CreateResponseContent::from(
            $result
        ), $attributes['content']);

        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['role'],
            $attributes['model'],
            $attributes['stop_sequence'],
            $attributes['stop_reason'],
            isset($attributes['stop_details']) ? CreateResponseStopDetails::from($attributes['stop_details']) : null,
            $content,
            CreateResponseUsage::from($attributes['usage']),
            $attributes['container'] ?? null,
            $attributes['context_management'] ?? null,
            isset($attributes['diagnostics']) ? CreateResponseDiagnostics::from($attributes['diagnostics']) : null,
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'role' => $this->role,
            'model' => $this->model,
            'stop_sequence' => $this->stop_sequence,
            'usage' => $this->usage->toArray(),
            'content' => array_map(
                static fn (CreateResponseContent $result): array => $result->toArray(),
                $this->content,
            ),
            'stop_reason' => $this->stop_reason,
        ];

        if ($this->stop_details !== null) {
            $result['stop_details'] = $this->stop_details->toArray();
        }

        if ($this->container !== null) {
            $result['container'] = $this->container;
        }

        if ($this->context_management !== null) {
            $result['context_management'] = $this->context_management;
        }

        if ($this->diagnostics !== null) {
            $result['diagnostics'] = $this->diagnostics->toArray();
        }

        return $result;
    }
}
