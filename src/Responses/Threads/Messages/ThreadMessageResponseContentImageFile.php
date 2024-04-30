<?php

declare(strict_types=1);

namespace Anthropic\Responses\Threads\Messages;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{file_id: string}>
 */
final class ThreadMessageResponseContentImageFile implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{file_id: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public string $fileId,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{file_id: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['file_id'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'file_id' => $this->fileId,
        ];
    }
}
