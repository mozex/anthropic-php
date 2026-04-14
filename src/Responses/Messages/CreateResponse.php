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
 * @implements ResponseContract<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>, inference_geo?: string}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null}>, stop_reason: string, stop_details?: array{type: string, category: string|null, explanation: string|null}, container?: array{id: string, expires_at: string}}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>, inference_geo?: string}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null}>, stop_reason: string, stop_details?: array{type: string, category: string|null, explanation: string|null}, container?: array{id: string, expires_at: string}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, CreateResponseContent>  $content
     * @param  array{id: string, expires_at: string}|null  $container
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
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int|null, cache_read_input_tokens?: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}|null, inference_geo?: string|null}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null, tool_use_id?: string|null, content?: array<int|string, mixed>|null, citations?: array<int|string, mixed>|null, caller?: array{type: string, tool_id?: string|null}|null, file_id?: string|null}>, stop_reason: string, stop_details?: array{type: string, category?: string|null, explanation?: string|null}|null, container?: array{id: string, expires_at: string}|null}  $attributes
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

        return $result;
    }
}
