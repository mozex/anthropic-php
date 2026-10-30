<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Messages;

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Testing\Responses\Concerns\FakesStreamBodies;
use ArrayObject;

trait FakeableForStreamedResponse
{
    use FakesStreamBodies;

    /**
     * A stream of the fixture's events, or of a stream resource, a list of events (each an array with its `type`), or
     * the events the API would send for a `CreateResponse`. `$headers` go on the response, which `meta()` reads; without
     * them, a `CreateResponse`'s own meta, else the other fakes' rate-limit headers and request ID.
     *
     * @param  resource|list<array<string, mixed>>|CreateResponse|null  $source
     * @param  array<string, string|list<string>>|null  $headers
     */
    public static function fake($source = null, ?array $headers = null): StreamResponse
    {
        if ($source instanceof CreateResponse) {
            $headers ??= self::headersOf($source);
            $source = self::eventsOf($source);
        }

        if (is_array($source)) {
            $source = implode('', array_map(
                fn (array $event): string => 'event: '.$event['type']."\ndata: ".json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n\n",
                $source,
            ));
        }

        $source ??= fopen(str_replace(['Anthropic\Responses', '\\'], [__DIR__.'/../../Fixtures/', '/'], self::class).'Fixture.txt', 'r');

        return new StreamResponse(self::class, self::fakeHttpResponse($source, $headers ?? self::fakeMetaHeaders()));
    }

    /**
     * The events a finished message streams as. `message_start` carries the message without its content, its stop
     * and its late fields, and only the input usage. Each block opens emptied and is filled by its deltas: text and
     * a `citations_delta` per citation, thinking and its signature, a tool call's input as JSON, a threshold
     * compaction's one `compaction_delta`. A block of another type opens whole. `message_delta` then carries the stop
     * reason, `stop_details`, `container`, `context_management` and the cumulative usage (the keys the API's delta
     * usage has), before `message_stop`.
     *
     * @return list<array<string, mixed>>
     */
    private static function eventsOf(CreateResponse $response): array
    {
        $message = $response->toArray();
        $usage = $message['usage'];
        $late = ['stop_details', 'container', 'context_management'];

        $events = [['type' => 'message_start', 'message' => [
            ...array_diff_key($message, array_flip($late)),
            'content' => [],
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => [...array_diff_key($usage, array_flip(['iterations', 'server_tool_use', 'output_tokens_details', 'fallback_credit'])), 'output_tokens' => 1],
        ]]];

        foreach (array_values($message['content']) as $index => $block) {
            [$start, $deltas] = match (true) {
                $block['type'] === 'text' => [['type' => 'text', 'text' => ''], [
                    ['type' => 'text_delta', 'text' => $block['text']],
                    ...array_map(fn (array $citation): array => ['type' => 'citations_delta', 'citation' => $citation], $block['citations'] ?? []),
                ]],
                $block['type'] === 'thinking' => [['type' => 'thinking', 'thinking' => '', 'signature' => ''], [
                    ['type' => 'thinking_delta', 'thinking' => $block['thinking']],
                    ...(is_string($block['signature'] ?? null) ? [['type' => 'signature_delta', 'signature' => $block['signature']]] : []),
                ]],
                in_array($block['type'], ['tool_use', 'server_tool_use', 'mcp_tool_use'], true) => [[...$block, 'input' => new ArrayObject], [
                    ['type' => 'input_json_delta', 'partial_json' => json_encode($block['input'] ?? new ArrayObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ]],
                // A threshold compaction (one without a signature) fills in one delta; one on demand opens whole.
                $block['type'] === 'compaction' && ($block['signature'] ?? null) === null => [['type' => 'compaction', 'content' => null], [
                    ['type' => 'compaction_delta', ...array_intersect_key($block, array_flip(['content', 'encrypted_content']))],
                ]],
                default => [$block, []],
            };

            $events[] = ['type' => 'content_block_start', 'index' => $index, 'content_block' => $start];

            foreach ($deltas as $delta) {
                $events[] = ['type' => 'content_block_delta', 'index' => $index, 'delta' => $delta];
            }

            $events[] = ['type' => 'content_block_stop', 'index' => $index];
        }

        $delta = ['type' => 'message_delta', 'delta' => [
            'stop_reason' => $message['stop_reason'],
            'stop_sequence' => $message['stop_sequence'],
            ...array_intersect_key($message, array_flip(['stop_details', 'container'])),
        ], 'usage' => array_intersect_key($usage, array_flip([
            'input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens', 'server_tool_use',
            'iterations', 'output_tokens_details', 'fallback_credit',
        ]))];

        if (array_key_exists('context_management', $message)) {
            $delta['context_management'] = $message['context_management'];
        }

        $events[] = $delta;
        $events[] = ['type' => 'message_stop'];

        return $events;
    }

    /**
     * A response's own meta as the headers it came with, but its content type, which a stream's isn't.
     *
     * @return array<string, string>
     */
    private static function headersOf(CreateResponse $response): array
    {
        $headers = [];

        foreach ($response->meta()->toArray() as $name => $value) {
            $lines = $name === 'custom' && is_array($value) ? $value : [$name => $value];

            foreach ($lines as $header => $line) {
                if (is_scalar($line) && strtolower((string) $header) !== 'content-type') {
                    $headers[(string) $header] = (string) $line;
                }
            }
        }

        return $headers;
    }
}
