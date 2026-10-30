<?php

declare(strict_types=1);

namespace Anthropic\Resources\Concerns;

use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Responses\Messages\CountTokensResponse;

trait CountsToFit
{
    abstract public function countTokens(array $parameters): CountTokensResponse;

    /**
     * Whether these Messages parameters fit in `$limit` input tokens, as the API counts them (`count_tokens`, which
     * bills nothing). Only what `count_tokens` takes is sent, so `create()`'s parameters can be passed as they are.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function fits(array $parameters, int $limit): bool
    {
        return $this->inputTokens($parameters) <= $limit;
    }

    /**
     * These parameters with the oldest turns of `messages` dropped until they fit in `$limit` input tokens; as they are
     * when they already fit. A cut only falls before a user turn without tool results, so every tool call keeps its
     * result and the conversation still starts with the user; the system prompt and the tools stay, while a `system`
     * message among the dropped turns goes with them. It counts a few times (the full request, then a binary search
     * over the cuts), and throws when even the last cut doesn't fit. Thinking blocks kept after a cut were made with
     * the dropped history in place, which Claude 5.1 models reject unless `thinking.block_binding` says to drop them.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function trimToFit(array $parameters, int $limit): array
    {
        if (! is_array($parameters['messages'] ?? null)) {
            throw new InvalidArgumentException('trimToFit() needs `messages` as an array of turns.');
        }

        $tokens = $this->inputTokens($parameters);

        if ($tokens <= $limit) {
            return $parameters;
        }

        $messages = array_values($parameters['messages']);
        $from = fn (int $cut): array => [...$parameters, 'messages' => array_slice($messages, $cut)];
        $cuts = array_values(array_filter(array_keys($messages), fn (int $index): bool => $index > 0 && self::startsTurn($messages[$index])));

        if ($cuts !== []) {
            $tokens = $this->inputTokens($from($cuts[count($cuts) - 1]));
        }

        if ($tokens > $limit) {
            throw new InvalidArgumentException("Even the last turn the conversation can start from is {$tokens} input tokens, over the limit of {$limit}: shorten it, or raise the limit.");
        }

        // Fewer turns almost never count more, so a cut that fits is found by halving; context editing can make a
        // longer history count less, and then it cuts more than it had to, never too little.
        [$low, $high] = [0, count($cuts) - 1];

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->fits($from($cuts[$middle]), $limit)) {
                $high = $middle;
            } else {
                $low = $middle + 1;
            }
        }

        return $from($cuts[$low]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function inputTokens(array $parameters): int
    {
        // What count_tokens takes: the reference's parameters, the official SDK's beta ones (v1.3.0), and `betas`,
        // which goes out as the `anthropic-beta` header the beta parameters need.
        $counted = ['betas', 'cache_control', 'compaction', 'context_management', 'mcp_servers', 'messages', 'model', 'output_config', 'output_format', 'speed', 'system', 'thinking', 'tool_choice', 'tools'];

        return $this->countTokens(array_intersect_key($parameters, array_flip($counted)))->inputTokens;
    }

    /**
     * Whether a conversation can start at this message: a user turn that answers no tool call.
     */
    private static function startsTurn(mixed $message): bool
    {
        if (self::field($message, 'role') !== 'user') {
            return false;
        }

        $content = self::field($message, 'content');

        if (! is_array($content)) {
            return true;
        }

        foreach ($content as $block) {
            if (self::field($block, 'type') === 'tool_result') {
                return false;
            }
        }

        return true;
    }

    /**
     * A key of a message or a block, decoded as an array or as an object.
     */
    private static function field(mixed $value, string $key): mixed
    {
        return match (true) {
            is_array($value) => $value[$key] ?? null,
            is_object($value) => $value->{$key} ?? null,
            default => null,
        };
    }
}
