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
 * @implements ResponseContract<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, string>|null}>, stop_reason: string}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, string>|null}>, stop_reason: string}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, CreateResponseContent>  $content
     */
    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $role,
        public readonly string $model,
        public readonly ?string $stop_sequence,
        public readonly string $stop_reason,
        public readonly array $content,
        public readonly CreateResponseUsage $usage,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null}, content: array<int, array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, string>|null}>, stop_reason: string}  $attributes
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
            $content,
            CreateResponseUsage::from($attributes['usage']),
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
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
    }
}
