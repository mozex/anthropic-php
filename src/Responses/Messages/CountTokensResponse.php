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
 * @implements ResponseContract<array{input_tokens: int, context_management?: array{original_input_tokens: int}|null}>
 */
class CountTokensResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{input_tokens: int, context_management?: array{original_input_tokens: int}|null}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array{original_input_tokens: int}|null  $contextManagement
     * @param  array{input_tokens: int, context_management?: array{original_input_tokens: int}|null}  $attributes
     */
    private function __construct(
        public readonly int $inputTokens,
        public readonly ?array $contextManagement,
        private readonly MetaInformation $meta,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{input_tokens: int, context_management?: array{original_input_tokens: int}|null}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['input_tokens'],
            $attributes['context_management'] ?? null,
            $meta,
            $attributes,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'input_tokens' => $this->inputTokens,
        ]);
    }
}
