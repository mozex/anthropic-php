<?php

declare(strict_types=1);

namespace Anthropic\Responses\Completions;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Testing\Responses\Concerns\Completions\Fakeable;

/**
 * @implements ResponseContract<array{type: string, id: string, completion: string, stop_reason: string, model: string, stop: string, log_id: string}>
 */
final class CreateResponse implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string, id: string, completion: string, stop_reason: string, model: string, stop: string, log_id: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $completion,
        public readonly string $stop_reason,
        public readonly string $model,
        public readonly string $stop,
        public readonly string $log_id,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: string, id: string, completion: string, stop_reason: string, model: string, stop: string, log_id: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['id'],
            $attributes['completion'],
            $attributes['stop_reason'],
            $attributes['model'],
            $attributes['stop'],
            $attributes['log_id'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'completion' => $this->completion,
            'stop_reason' => $this->stop_reason,
            'model' => $this->model,
            'stop' => $this->stop,
            'log_id' => $this->log_id,
        ];
    }
}
