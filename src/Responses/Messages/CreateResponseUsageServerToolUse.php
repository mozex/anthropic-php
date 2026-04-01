<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsageServerToolUse
{
    private function __construct(
        public readonly int $webSearchRequests,
        public readonly int $webFetchRequests,
        public readonly int $codeExecutionRequests,
        public readonly int $toolSearchRequests,
    ) {}

    /**
     * @param  array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['web_search_requests'] ?? 0,
            $attributes['web_fetch_requests'] ?? 0,
            $attributes['code_execution_requests'] ?? 0,
            $attributes['tool_search_requests'] ?? 0,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return array_filter([
            'web_search_requests' => $this->webSearchRequests,
            'web_fetch_requests' => $this->webFetchRequests,
            'code_execution_requests' => $this->codeExecutionRequests,
            'tool_search_requests' => $this->toolSearchRequests,
        ]);
    }
}
