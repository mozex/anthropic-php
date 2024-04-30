<?php

declare(strict_types=1);

namespace Anthropic\Responses\Assistants;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{type: string}>
 */
final class AssistantResponseToolCodeInterpreter implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public string $type,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'code_interpreter'}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
