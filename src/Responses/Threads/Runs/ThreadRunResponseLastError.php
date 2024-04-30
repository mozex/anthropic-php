<?php

declare(strict_types=1);

namespace Anthropic\Responses\Threads\Runs;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{code: string, message: string}>
 */
final class ThreadRunResponseLastError implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{code: string, message: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public string $code,
        public string $message,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{code: string, message: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['code'],
            $attributes['message'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
