<?php

declare(strict_types=1);

namespace Anthropic\Responses\Threads\Runs\Steps;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{type: string, logs: string}>
 */
final class ThreadRunStepResponseCodeInterpreterOutputLogs implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string, logs: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public string $type,
        public string $logs,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'logs', logs: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['logs'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'logs' => $this->logs,
        ];
    }
}
