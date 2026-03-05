<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsageServerToolUse
{
    private function __construct(
        public readonly int $webSearchRequests,
    ) {}

    /**
     * @param  array{web_search_requests: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['web_search_requests'],
        );
    }

    /**
     * @return array{web_search_requests: int}
     */
    public function toArray(): array
    {
        return [
            'web_search_requests' => $this->webSearchRequests,
        ];
    }
}
