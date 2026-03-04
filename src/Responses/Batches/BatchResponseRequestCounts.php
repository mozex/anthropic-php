<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

final class BatchResponseRequestCounts
{
    private function __construct(
        public readonly int $processing,
        public readonly int $succeeded,
        public readonly int $errored,
        public readonly int $canceled,
        public readonly int $expired,
    ) {}

    /**
     * @param  array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['processing'],
            $attributes['succeeded'],
            $attributes['errored'],
            $attributes['canceled'],
            $attributes['expired'],
        );
    }

    /**
     * @return array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}
     */
    public function toArray(): array
    {
        return [
            'processing' => $this->processing,
            'succeeded' => $this->succeeded,
            'errored' => $this->errored,
            'canceled' => $this->canceled,
            'expired' => $this->expired,
        ];
    }
}
