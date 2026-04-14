<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Models\Fakeable;

/**
 * @implements ResponseContract<array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}>
 */
final class RetrieveResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $createdAt,
        public readonly string $displayName,
        public readonly int $maxInputTokens,
        public readonly int $maxTokens,
        public readonly RetrieveResponseCapabilities $capabilities,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['created_at'],
            $attributes['display_name'],
            $attributes['max_input_tokens'],
            $attributes['max_tokens'],
            RetrieveResponseCapabilities::from($attributes['capabilities']),
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
            'created_at' => $this->createdAt,
            'display_name' => $this->displayName,
            'max_input_tokens' => $this->maxInputTokens,
            'max_tokens' => $this->maxTokens,
            'capabilities' => $this->capabilities->toArray(),
        ];
    }
}
