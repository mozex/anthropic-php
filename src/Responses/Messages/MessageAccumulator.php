<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Responses\Meta\MetaInformation;
use ArrayObject;

/**
 * Folds a Messages stream's events into the message they add up to, as the API would have sent it whole: `message_start`
 * seeds it, each block's deltas fill it (text, thinking and its signature, citations, a tool call's input JSON, decoded
 * when the block stops, a compaction's content), and `message_delta` sets the stop, the late fields and the cumulative
 * usage. Every stream's `finalMessage()` uses one. It takes events read any other way too, each as the API sent it,
 * decoded: the `data:` of an SSE event. An event's `toArray()` has another shape.
 *
 * Its content gains no key the events didn't carry, so it can be sent back. An event before `message_start`, a second
 * one, a block that skips an index, a delta for a block that hasn't started and an event of a type it doesn't know are
 * skipped, so folding a stream never fails on one.
 */
class MessageAccumulator
{
    /**
     * The message so far, in the API's shape.
     *
     * @var array<array-key, mixed>|null
     */
    private ?array $message = null;

    /**
     * Each open tool call's input JSON so far, by block index.
     *
     * @var array<int, string>
     */
    private array $inputs = [];

    private bool $completed = false;

    /**
     * @param  array<array-key, mixed>  $event
     */
    public function add(array $event): self
    {
        $type = $event['type'] ?? null;

        if ($type === 'message_start') {
            if ($this->message === null && is_array($event['message'] ?? null)) {
                $this->message = $event['message'];
            }

            return $this;
        }

        if ($type === 'message_stop') {
            $this->completed = true;

            return $this;
        }

        if ($this->message === null) {
            return $this;
        }

        match ($type) {
            'content_block_start' => $this->start($event),
            'content_block_delta' => $this->delta($event),
            'content_block_stop' => $this->stop($event),
            'message_delta' => $this->messageDelta($event),
            default => null,
        };

        return $this;
    }

    /**
     * The message so far, null before `message_start`: mid-stream, its stop reason is null and an open tool call's
     * input is what its JSON decodes to so far, else the start's `{}`. It assumes `message_start`'s envelope (`id`,
     * `type`, `role`, `model`), which the API always sends.
     */
    public function message(?MetaInformation $meta = null): ?CreateResponse
    {
        $message = $this->toArray();

        if ($message === null) {
            return null;
        }

        // Fields the response always has, which a stream can leave out: zero counts when none were streamed.
        $message += ['stop_reason' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0]];

        /** @phpstan-ignore argument.type (the events' shape, which the API guarantees) */
        return CreateResponse::from($message, $meta ?? MetaInformation::from([]));
    }

    /**
     * The message so far in the API's shape, null before `message_start`. A tool call's empty input is an `ArrayObject`,
     * which encodes as `{}`, as the API wants it back.
     *
     * @return array<array-key, mixed>|null
     */
    public function toArray(): ?array
    {
        if ($this->message === null) {
            return null;
        }

        $message = $this->message;

        foreach ($this->inputs as $index => $json) {
            self::withInput($message, $index, $json);
        }

        if (is_array($message['content'] ?? null)) {
            $message['content'] = array_map(
                fn (mixed $block): mixed => is_array($block) && in_array($block['type'] ?? null, ['tool_use', 'server_tool_use', 'mcp_tool_use'], true) && ($block['input'] ?? null) === []
                    ? array_replace($block, ['input' => new ArrayObject])
                    : $block,
                $message['content'],
            );
        }

        return $message;
    }

    /**
     * Whether `message_stop` was folded.
     */
    public function completed(): bool
    {
        return $this->completed;
    }

    /**
     * The usage so far: `message_start`'s, with each `message_delta`'s counts over it (a null one left out).
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        $usage = $this->message['usage'] ?? null;

        /** @var array<string, mixed> */
        return is_array($usage) ? $usage : [];
    }

    /**
     * The model that answered: `message_start`'s, or after a server-side fallback the one the `fallback` block hands
     * to and the final `usage.iterations` names; null before `message_start`.
     */
    public function model(): ?string
    {
        $model = $this->message['model'] ?? null;

        return is_string($model) ? $model : null;
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function start(array $event): void
    {
        $block = $event['content_block'] ?? null;

        if (! is_array($this->message['content'] ?? null)) {
            $this->message['content'] = [];
        }

        // Blocks start in index order, each right after the last.
        if (! is_array($block) || ($event['index'] ?? null) !== count($this->message['content'])) {
            return;
        }

        $this->message['content'][] = $block;

        // A fallback mid-output: message_start named the model that declined, this block the one that answers.
        if (($block['type'] ?? null) === 'fallback' && is_array($block['to'] ?? null) && is_string($block['to']['model'] ?? null)) {
            $this->message['model'] = $block['to']['model'];
        }
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function delta(array $event): void
    {
        $index = $event['index'] ?? null;
        $delta = $event['delta'] ?? null;
        $content = $this->message['content'] ?? null;

        if (! is_int($index) || ! is_array($delta) || ! is_array($content) || ! is_array($content[$index] ?? null)) {
            return;
        }

        $type = $delta['type'] ?? null;

        if ($type === 'input_json_delta') {
            $this->inputs[$index] ??= '';
            $this->inputs[$index] .= self::string($delta['partial_json'] ?? null);

            return;
        }

        // The block is taken out while it's added to, so PHP extends its text in place rather than copying it, and a
        // long answer costs no more than its length.
        $this->message['content'] = null;
        $block = $content[$index];
        $content[$index] = null;

        match ($type) {
            'text_delta' => self::append($block, 'text', self::string($delta['text'] ?? null)),
            'thinking_delta' => self::append($block, 'thinking', self::string($delta['thinking'] ?? null)),
            'signature_delta' => self::append($block, 'signature', self::string($delta['signature'] ?? null)),
            'citations_delta' => self::cite($block, $delta['citation'] ?? null),
            'compaction_delta' => self::compact($block, $delta),
            default => null,
        };

        $content[$index] = $block;
        $this->message['content'] = $content;
    }

    /**
     * @param  array<array-key, mixed>  $block
     */
    private static function append(array &$block, string $field, string $text): void
    {
        $value = is_string($block[$field] ?? null) ? $block[$field] : '';
        $block[$field] = null;
        $value .= $text;
        $block[$field] = $value;
    }

    /**
     * @param  array<array-key, mixed>  $block
     */
    private static function cite(array &$block, mixed $citation): void
    {
        $citations = is_array($block['citations'] ?? null) ? $block['citations'] : [];
        $block['citations'] = null;
        $citations[] = $citation;
        $block['citations'] = $citations;
    }

    /**
     * Only the keys the delta sends: a replayed compaction block with one more is refused.
     *
     * @param  array<array-key, mixed>  $block
     * @param  array<array-key, mixed>  $delta
     */
    private static function compact(array &$block, array $delta): void
    {
        if (is_string($delta['content'] ?? null)) {
            self::append($block, 'content', $delta['content']);
        } elseif (array_key_exists('content', $delta) && ! array_key_exists('content', $block)) {
            $block['content'] = $delta['content'];
        }

        if (array_key_exists('encrypted_content', $delta)) {
            $block['encrypted_content'] = $delta['encrypted_content'];
        }
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function stop(array $event): void
    {
        $index = $event['index'] ?? null;

        if (! is_int($index) || ! isset($this->inputs[$index])) {
            return;
        }

        $message = $this->message ?? [];
        self::withInput($message, $index, $this->inputs[$index]);
        $this->message = $message;
        unset($this->inputs[$index]);
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function messageDelta(array $event): void
    {
        $message = $this->message ?? [];

        // The stop fields are final, null included; the rest (container, context_management...) replace only when sent.
        foreach (is_array($event['delta'] ?? null) ? $event['delta'] : [] as $key => $value) {
            if ($key !== 'type' && ($value !== null || in_array($key, ['stop_reason', 'stop_sequence', 'stop_details'], true) || ! array_key_exists($key, $message))) {
                $message[$key] = $value;
            }
        }

        foreach ($event as $key => $value) {
            if (! in_array($key, ['type', 'delta', 'usage'], true) && ($value !== null || ! array_key_exists($key, $message))) {
                $message[$key] = $value;
            }
        }

        // The counts are cumulative, so each one sent replaces message_start's.
        if (is_array($event['usage'] ?? null)) {
            $usage = is_array($message['usage'] ?? null) ? $message['usage'] : [];
            $message['usage'] = array_replace($usage, array_filter($event['usage'], fn (mixed $value): bool => $value !== null));

            // A turn the API served straight from the fallback carries no fallback block, only this iteration.
            foreach (is_array($message['usage']['iterations'] ?? null) ? $message['usage']['iterations'] : [] as $iteration) {
                if (is_array($iteration) && ($iteration['type'] ?? null) === 'fallback_message' && is_string($iteration['model'] ?? null)) {
                    $message['model'] = $iteration['model'];
                }
            }
        }

        $this->message = $message;
    }

    /**
     * Puts a tool call's input in its block, when the JSON so far decodes; else the start's input stays.
     *
     * @param  array<array-key, mixed>  $message
     */
    private static function withInput(array &$message, int $index, string $json): void
    {
        $input = json_decode($json, true);

        if (is_array($input) && is_array($message['content'] ?? null) && is_array($message['content'][$index] ?? null)) {
            $message['content'][$index]['input'] = $input;
        }
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
