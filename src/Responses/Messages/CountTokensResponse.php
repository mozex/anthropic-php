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
 * @implements ResponseContract<array{input_tokens: int}>
 */
class CountTokensResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{input_tokens: int}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array{input_tokens: int}  $attributes
     */
    private function __construct(
        public readonly int $inputTokens,
        private readonly MetaInformation $meta,
        protected readonly array $attributes,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{input_tokens: int}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['input_tokens'],
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
