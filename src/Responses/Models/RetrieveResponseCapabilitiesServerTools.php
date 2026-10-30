<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitiesServerTools
{
    /**
     * @param  array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}  $attributes
     */
    private function __construct(
        public readonly bool $supported,
        public readonly RetrieveResponseCapabilitySupport $codeExecution,
        public readonly RetrieveResponseCapabilitySupport $webSearch,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            RetrieveResponseCapabilitySupport::from($attributes['code_execution']),
            RetrieveResponseCapabilitySupport::from($attributes['web_search']),
            $attributes,
        );
    }

    /**
     * @return array{supported: bool, code_execution: array{supported: bool}, web_search: array{supported: bool}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'supported' => $this->supported,
            'code_execution' => $this->codeExecution->toArray(),
            'web_search' => $this->webSearch->toArray(),
        ]);
    }
}
