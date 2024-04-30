<?php

declare(strict_types=1);

namespace Anthropic\Responses\Assistants\Files;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{id: string, object: string, created_at: int, assistant_id: string}>
 */
final class AssistantFileResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, object: string, created_at: int, assistant_id: string}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    private function __construct(
        public string $id,
        public string $object,
        public int $createdAt,
        public string $assistantId,
        private readonly MetaInformation $meta,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, object: string, created_at: int, assistant_id: string}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['object'],
            $attributes['created_at'],
            $attributes['assistant_id'],
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
            'object' => $this->object,
            'created_at' => $this->createdAt,
            'assistant_id' => $this->assistantId,
        ];
    }
}
