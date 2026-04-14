<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilities
{
    private function __construct(
        public readonly RetrieveResponseCapabilitySupport $batch,
        public readonly RetrieveResponseCapabilitySupport $citations,
        public readonly RetrieveResponseCapabilitySupport $codeExecution,
        public readonly RetrieveResponseCapabilitiesContextManagement $contextManagement,
        public readonly RetrieveResponseCapabilitiesEffort $effort,
        public readonly RetrieveResponseCapabilitySupport $imageInput,
        public readonly RetrieveResponseCapabilitySupport $pdfInput,
        public readonly RetrieveResponseCapabilitySupport $structuredOutputs,
        public readonly RetrieveResponseCapabilitiesThinking $thinking,
    ) {}

    /**
     * @param  array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            RetrieveResponseCapabilitySupport::from($attributes['batch']),
            RetrieveResponseCapabilitySupport::from($attributes['citations']),
            RetrieveResponseCapabilitySupport::from($attributes['code_execution']),
            RetrieveResponseCapabilitiesContextManagement::from($attributes['context_management']),
            RetrieveResponseCapabilitiesEffort::from($attributes['effort']),
            RetrieveResponseCapabilitySupport::from($attributes['image_input']),
            RetrieveResponseCapabilitySupport::from($attributes['pdf_input']),
            RetrieveResponseCapabilitySupport::from($attributes['structured_outputs']),
            RetrieveResponseCapabilitiesThinking::from($attributes['thinking']),
        );
    }

    /**
     * @return array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}
     */
    public function toArray(): array
    {
        return [
            'batch' => $this->batch->toArray(),
            'citations' => $this->citations->toArray(),
            'code_execution' => $this->codeExecution->toArray(),
            'context_management' => $this->contextManagement->toArray(),
            'effort' => $this->effort->toArray(),
            'image_input' => $this->imageInput->toArray(),
            'pdf_input' => $this->pdfInput->toArray(),
            'structured_outputs' => $this->structuredOutputs->toArray(),
            'thinking' => $this->thinking->toArray(),
        ];
    }
}
