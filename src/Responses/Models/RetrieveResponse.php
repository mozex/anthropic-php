<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Models\Fakeable;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * @implements ResponseContract<array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int|null, max_tokens: int|null, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}|null>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}, xhigh?: array{supported: bool}|null}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}}, server_tools?: array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}, compaction?: array{supported: bool, summarize: array{supported: bool}}|null}|null, deprecated_at?: string|null, lifecycle?: string, line?: string|null, retires_at?: string|null, allowed_fallback_models?: array<int, string>|null}>
 */
class RetrieveResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int|null, max_tokens: int|null, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}|null>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}, xhigh?: array{supported: bool}|null}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}}, server_tools?: array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}, compaction?: array{supported: bool, summarize: array{supported: bool}}|null}|null, deprecated_at?: string|null, lifecycle?: string, line?: string|null, retires_at?: string|null, allowed_fallback_models?: array<int, string>|null}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, string>|null  $allowedFallbackModels
     * @param  array{id: string, type: string, created_at: string, display_name: string, max_input_tokens?: int|null, max_tokens?: int|null, capabilities?: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}|null>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}, xhigh?: array{supported: bool}|null}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}}, server_tools?: array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}, compaction?: array{supported: bool, summarize: array{supported: bool}}|null}|null, deprecated_at?: string|null, lifecycle?: string, line?: string|null, retires_at?: string|null, allowed_fallback_models?: array<int, string>|null}  $attributes
     */
    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $createdAt,
        public readonly string $displayName,
        public readonly ?int $maxInputTokens,
        public readonly ?int $maxTokens,
        public readonly ?RetrieveResponseCapabilities $capabilities,
        public readonly ?string $deprecatedAt,
        public readonly ?string $lifecycle,
        public readonly ?string $line,
        public readonly ?string $retiresAt,
        public readonly ?array $allowedFallbackModels,
        private readonly MetaInformation $meta,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, created_at: string, display_name: string, max_input_tokens?: int|null, max_tokens?: int|null, capabilities?: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}|null>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}, xhigh?: array{supported: bool}|null}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}}, server_tools?: array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}, compaction?: array{supported: bool, summarize: array{supported: bool}}|null}|null, deprecated_at?: string|null, lifecycle?: string, line?: string|null, retires_at?: string|null, allowed_fallback_models?: array<int, string>|null}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['created_at'],
            $attributes['display_name'],
            $attributes['max_input_tokens'] ?? null,
            $attributes['max_tokens'] ?? null,
            isset($attributes['capabilities']) ? RetrieveResponseCapabilities::from($attributes['capabilities']) : null,
            $attributes['deprecated_at'] ?? null,
            $attributes['lifecycle'] ?? null,
            $attributes['line'] ?? null,
            $attributes['retires_at'] ?? null,
            $attributes['allowed_fallback_models'] ?? null,
            $meta,
            $attributes,
        );
    }

    /**
     * The model's scheduled retirement, from `retires_at`, or null when the API sends none (or one that isn't an
     * RFC 3339 date). The schedule can move until the model retires.
     */
    public function retirementDate(): ?DateTimeImmutable
    {
        if ($this->retiresAt === null || preg_match('/^\d{4}-\d{2}-\d{2}T/', $this->retiresAt) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($this->retiresAt);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Whether the model has retired. `lifecycle` is the API's retirement signal, so a deprecated model past its date
     * isn't retired: see isRetirementOverdue().
     */
    public function isRetired(): bool
    {
        return $this->lifecycle === 'retired';
    }

    /**
     * Whether the model still answers but is on its way out: `deprecated`.
     */
    public function isRetiring(): bool
    {
        return $this->lifecycle === 'deprecated';
    }

    /**
     * Whether a deprecated model's retirement date has passed: the API calls it overdue, not retired, and the model may
     * stop answering at any time.
     */
    public function isRetirementOverdue(?DateTimeInterface $now = null): bool
    {
        $retirement = $this->retirementDate();

        return $this->isRetiring() && $retirement !== null && $retirement <= ($now ?? new DateTimeImmutable);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'id' => $this->id,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'display_name' => $this->displayName,
            'max_input_tokens' => $this->maxInputTokens,
            'max_tokens' => $this->maxTokens,
            'capabilities' => $this->capabilities?->toArray(),
        ]);
    }
}
