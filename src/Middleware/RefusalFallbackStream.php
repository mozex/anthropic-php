<?php

declare(strict_types=1);

namespace Anthropic\Middleware;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * The body of a streamed request `RefusalFallbackMiddleware` handles: the answer's events as they come, and when it
 * refuses, the next model's, spliced on behind a `fallback` block, so the reader gets one message. Each fallback is
 * requested while the stream is read.
 *
 * - The answer's `message_start` waits for its first block, so a refusal before any output costs nothing to redo. It
 *   keeps the first answer's message ID, and a fallback's own start isn't sent.
 * - On a refusal with a credit token (or before any output), the blocks it cut open are closed, its `message_stop`
 *   swallowed, and the next model asked. With `fallback_has_prefill_claim`, the refused partial (after the last
 *   `fallback` block) goes along as an assistant turn the fallback carries on from.
 * - A fallback's blocks follow the `fallback` block, their indices counting on. The `fallback` block goes out only once
 *   the fallback answers with output, so a hop that fails leaves none.
 * - The final `message_delta`'s `usage.iterations` holds every refused attempt, then the answer's own, the last as
 *   `fallback_message`.
 * - When no fallback answers, the refusal goes out as it came, with the attempts' iterations.
 *
 * An event that changes is decoded with objects kept as objects, so an empty `{}` goes out as `{}`; the others pass
 * through as they came. Pings are dropped.
 *
 * @internal
 */
class RefusalFallbackStream implements StreamInterface
{
    /**
     * The spliced bytes not yet read.
     */
    private string $out = '';

    /**
     * The current answer's bytes not yet parsed.
     */
    private string $raw = '';

    private StreamInterface $body;

    private bool $upstreamEnded = false;

    private bool $done = false;

    private int $position = 0;

    /**
     * Whether `message_start` went out.
     */
    private bool $open = false;

    private string $messageId = '';

    /**
     * The next block index the reader gets, and where the current answer's index 0 lands.
     */
    private int $nextIndex = 0;

    private int $offset = 0;

    /**
     * The `fallback` blocks waiting for a fallback's first output, one per switch (a fallback that refuses before any
     * output adds its own): `from` and `to` as the API resolved them, and what to go back to when it never answers.
     *
     * @var list<array{from: string, to: string, category: mixed, model: string, served: string}>
     */
    private array $seams = [];

    /**
     * The model answering now, as the API resolved it (an alias's dated name), for the `fallback` blocks; `$model` is
     * the chain's own name, for skipping a model that refused and naming the attempts.
     */
    private string $served;

    /**
     * The attempts the API refused, as `usage.iterations` entries.
     *
     * @var list<array<array-key, mixed>>
     */
    private array $ledger = [];

    /**
     * The claimed partials, sent along as one assistant turn.
     *
     * @var list<stdClass>
     */
    private array $continuation = [];

    /**
     * The refusal a fallback is answering: its start, its `message_delta` and `message_stop`, the token and how many
     * blocks it claimed.
     *
     * @var array{start: ?string, suppressed: bool, delta: string, stop: string, token: ?string, claimed: int, message: array<string, mixed>}|null
     */
    private ?array $held = null;

    /**
     * The models that refused this request, never asked again.
     *
     * @var array<string, true>
     */
    private array $refused = [];

    /**
     * The category of the refusal a fallback answers, for its `fallback` block.
     */
    private mixed $category = null;

    // The current answer's own state, reset for each fallback.

    private bool $fallingBack = false;

    private bool $output = false;

    private bool $terminalSent = false;

    private bool $stopSent = false;

    private ?string $start = null;

    /**
     * Whether its `message_start` came after the stream opened, so it isn't sent.
     */
    private bool $startSuppressed = false;

    /**
     * The blocks it has open, by its own index.
     *
     * @var array<int, true>
     */
    private array $openBlocks = [];

    /**
     * Its blocks as they fold, by its own index, for a claim.
     *
     * @var array<int, stdClass>
     */
    private array $blocks = [];

    /**
     * Its tool inputs' JSON so far, by its own index.
     *
     * @var array<int, string>
     */
    private array $inputs = [];

    /**
     * @param  list<array<string, mixed>>  $chain
     * @param  Closure(array<string, mixed>, ?string, list<stdClass>, int): array{0: ?ResponseInterface, 1: int, 2: list<stdClass>}  $send  one hop: the response when it streams, else null, its status and the continuation it went with
     * @param  Closure(array<string, mixed>, array<string, mixed>, int): void  $onFallback
     * @param  Closure(int): void  $pin
     */
    public function __construct(
        ResponseInterface $response,
        private readonly array $chain,
        private int $next,
        private string $model,
        private readonly Closure $send,
        private readonly Closure $onFallback,
        private readonly Closure $pin,
    ) {
        $this->body = $response->getBody();
        $this->served = $model;
    }

    public function read(int $length): string
    {
        while ($this->out === '' && ! $this->done) {
            $this->advance();
        }

        $chunk = substr($this->out, 0, $length);
        $this->out = substr($this->out, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->done && $this->out === '';
    }

    public function getContents(): string
    {
        $contents = '';

        while (! $this->eof()) {
            $contents .= $this->read(65536);
        }

        return $contents;
    }

    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        $this->done = true;
        $this->out = '';
        $this->body->close();
    }

    public function detach(): null
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('A spliced stream is not seekable.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('A spliced stream is not seekable.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('A spliced stream is not writable.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }

    private function advance(): void
    {
        $event = $this->nextEvent();

        if ($event === null) {
            $this->ended();

            return;
        }

        [$type, $data] = $event;

        match ($type) {
            'ping' => null,
            'message_start' => $this->started($data),
            'content_block_start', 'content_block_delta', 'content_block_stop' => $this->block($type, $data),
            'message_delta' => $this->terminal($data),
            'message_stop' => $this->stopped($data),
            // A fallback whose stream fails before any output gives way to the next, as one that never answered does.
            'error' => $this->isSilentFallback() ? $this->abandon() : $this->failed($data),
            default => $this->emit($type, $data),
        };
    }

    /**
     * Whether the current answer is a fallback that has sent no output and no terminal yet, so the next entry can still
     * take its place.
     */
    private function isSilentFallback(): bool
    {
        return $this->fallingBack && ! $this->output && ! $this->terminalSent && $this->held !== null;
    }

    /**
     * The current answer ended: a fallback that ended without output gives way to the next one; else the stream ends,
     * with a `message_stop` an answer left out.
     */
    private function ended(): void
    {
        if ($this->isSilentFallback()) {
            $this->abandon();

            return;
        }

        // An answer that ended right after its start still sends it, so its usage is read.
        if (! $this->open && $this->start !== null) {
            $this->opened();
        }

        if ($this->terminalSent && ! $this->stopSent) {
            $this->emit('message_stop', '{"type":"message_stop"}');
        }

        $this->done = true;
    }

    private function started(string $data): void
    {
        $this->start = $data;
        $this->startSuppressed = $this->open;
        $message = self::decode($data)->message ?? null;

        if (! $this->fallingBack) {
            $this->messageId = $message instanceof stdClass && is_string($message->id ?? null) ? $message->id : '';
        } elseif ($message instanceof stdClass && is_string($message->model ?? null) && $this->seams !== []) {
            $this->seams[array_key_last($this->seams)]['to'] = $message->model;
            $this->served = $message->model;
        }
    }

    /**
     * An error the reader gets, after the held start, so the usage it had is still read.
     */
    private function failed(string $data): void
    {
        $this->opened();
        $this->emit('error', $data);
    }

    /**
     * A fallback that never answered with output: its `fallback` block goes, and the next entry is asked.
     */
    private function abandon(): void
    {
        $seam = array_pop($this->seams);

        if ($seam !== null) {
            $this->model = $seam['model'];
            $this->served = $seam['served'];
        }

        $this->fallBack();
    }

    private function block(string $type, string $data): void
    {
        $event = self::decode($data);
        $index = $event->index ?? null;

        if (! $event instanceof stdClass || ! is_int($index)) {
            $this->emit($type, $data);

            return;
        }

        $this->opened();
        $this->output = true;

        if ($type === 'content_block_start') {
            $this->openBlocks[$index] = true;
            $block = $event->content_block ?? null;
            $this->blocks[$index] = $block instanceof stdClass ? clone $block : new stdClass;
        } elseif ($type === 'content_block_stop') {
            unset($this->openBlocks[$index]);
        } elseif (($event->delta ?? null) instanceof stdClass && isset($this->blocks[$index])) {
            $this->fold($index, $event->delta);
        }

        $out = $this->offset + $index;
        $this->nextIndex = max($this->nextIndex, $out + 1);

        if ($out === $index) {
            $this->emit($type, $data);
        } else {
            $event->index = $out;
            $this->emit($type, self::encode($event));
        }
    }

    /**
     * Folds a delta into its block, for a claim: in place, so a long answer costs no more than its length.
     */
    private function fold(int $index, stdClass $delta): void
    {
        $block = $this->blocks[$index];

        if (($delta->type ?? null) === 'input_json_delta') {
            $this->inputs[$index] ??= '';
            $this->inputs[$index] .= is_string($delta->partial_json ?? null) ? $delta->partial_json : '';

            return;
        }

        match ($delta->type ?? null) {
            'text_delta' => self::append($block, 'text', $delta->text ?? null),
            'thinking_delta' => self::append($block, 'thinking', $delta->thinking ?? null),
            'signature_delta' => self::append($block, 'signature', $delta->signature ?? null),
            'citations_delta' => $block->citations = [...(is_array($block->citations ?? null) ? $block->citations : []), $delta->citation ?? null],
            'compaction_delta' => self::compact($block, $delta),
            default => null,
        };
    }

    private function terminal(string $data): void
    {
        $event = self::decode($data);
        $delta = $event->delta ?? null;
        $details = $delta instanceof stdClass && ($delta->stop_details ?? null) instanceof stdClass ? $delta->stop_details : null;
        $token = $details->fallback_credit_token ?? null;
        $token = is_string($token) && $token !== '' ? $token : null;

        $refused = $delta instanceof stdClass && ($delta->stop_reason ?? null) === 'refusal';

        if ($refused) {
            $this->refused[$this->model] = true;
        }

        // Once output streamed, only a token lets a fallback carry on; before any, it starts over for free.
        if (! $refused || ! $event instanceof stdClass || ($token === null && $this->output) || $this->nextEntry() === null) {
            $this->opened();
            $this->terminalSent = true;
            $this->emit('message_delta', $this->fallingBack && $event instanceof stdClass ? self::encode($this->stitched($event, $refused)) : $data);

            return;
        }

        $this->ledger = [...$this->ledger, ...$this->iterations($event, false)];
        $claimed = ($details->fallback_has_prefill_claim ?? null) === true ? $this->claim() : [];
        $this->continuation = [...$this->continuation, ...$claimed];

        $start = $this->start === null ? [] : json_decode($this->start, true);
        /** @var array<string, mixed> $message the refused message as it started, for the callback */
        $message = is_array($start) && is_array($start['message'] ?? null) ? $start['message'] : [];

        $this->held = [
            'start' => $this->start,
            'suppressed' => $this->startSuppressed,
            'delta' => $data,
            'stop' => $this->stopAhead(),
            'token' => $token,
            'claimed' => count($claimed),
            'message' => [...$message, 'stop_reason' => 'refusal', 'stop_details' => json_decode(self::encode($details ?? new stdClass), true)],
        ];
        $this->category = $details->category ?? null;

        // The blocks the refusal cut open close now, so a fallback or the refusal itself lands on whole blocks.
        foreach (array_keys($this->openBlocks) as $index) {
            $this->emit('content_block_stop', self::encode((object) ['type' => 'content_block_stop', 'index' => $this->offset + $index]));
        }

        $this->openBlocks = [];
        $this->fallBack();
    }

    /**
     * Asks the next models in turn, until one answers with a stream; else the refusal goes out as it came.
     */
    private function fallBack(): void
    {
        $held = $this->held ?? throw new RuntimeException('Nothing to fall back from.');
        $status = 0;
        $last = null;

        while (($index = $this->nextEntry()) !== null) {
            $entry = $this->chain[$index];
            $this->next = $index + 1;
            ($this->onFallback)($held['message'], $entry, $index);

            [$response, $status, $continuation] = ($this->send)($entry, $held['token'], $this->continuation, $held['claimed']);
            $last = $entry;

            if ($response !== null) {
                // A claim the 400 dropped is gone for good: a later entry's 400 has nothing more to trim.
                if (count($continuation) < count($this->continuation) && $this->held !== null) {
                    $this->held['claimed'] = 0;
                }

                $this->continuation = $continuation;
                $this->engage($response, $entry, $index);

                return;
            }
        }

        $this->degrade($status, $last);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function engage(ResponseInterface $response, array $entry, int $index): void
    {
        $to = is_string($entry['model'] ?? null) ? $entry['model'] : '';
        // `to` becomes the model the fallback's start names, once it comes.
        $this->seams[] = ['from' => $this->served, 'to' => $to, 'category' => $this->category, 'model' => $this->model, 'served' => $this->served];
        $this->model = $this->served = $to;

        $this->body->close();
        $this->body = $response->getBody();
        $this->raw = '';
        $this->upstreamEnded = false;

        $this->fallingBack = true;
        $this->output = $this->terminalSent = $this->stopSent = false;
        $this->start = null;
        $this->openBlocks = $this->blocks = $this->inputs = [];

        ($this->pin)($index);
    }

    /**
     * No fallback answered: the refusal goes out, with every attempt in `usage.iterations`, and the model the last one
     * named when it was rate limited.
     *
     * @param  array<string, mixed>|null  $last
     */
    private function degrade(int $status, ?array $last): void
    {
        $held = $this->held ?? throw new RuntimeException('Nothing to fall back from.');

        if (! $this->open) {
            $this->start = $held['start'];
        }

        $this->opened();
        $event = self::decode($held['delta']) ?? new stdClass;
        $details = ($event->delta ?? null) instanceof stdClass ? ($event->delta->stop_details ?? null) : null;

        if ($details instanceof stdClass && ($details->recommended_model ?? null) === null) {
            $details->recommended_model = $status === 429 && is_string($last['model'] ?? null) ? $last['model'] : null;
        }

        if ($this->ledger !== []) {
            self::putIterations($event, $this->ledger);
        }

        if ($held['suppressed']) {
            self::forwardTransformations($event, $held['start']);
        }

        $this->emit('message_delta', self::encode($event));
        $this->emit('message_stop', $held['stop']);
        $this->terminalSent = $this->stopSent = true;
        $this->done = true;
    }

    private function stopped(string $data): void
    {
        // A fallback's stop comes only behind its own message_delta.
        if ($this->fallingBack && ! $this->terminalSent) {
            return;
        }

        $this->stopSent = true;
        $this->emit('message_stop', $data);
    }

    /**
     * Opens the stream with the held `message_start` (the first answer's ID on it), then the waiting `fallback` block.
     */
    private function opened(): void
    {
        if (! $this->open) {
            $this->open = true;

            if ($this->start !== null) {
                $start = self::decode($this->start);
                $message = $start->message ?? null;

                if ($start !== null && $message instanceof stdClass && $this->messageId !== '' && ($message->id ?? null) !== $this->messageId) {
                    $message->id = $this->messageId;
                    $this->emit('message_start', self::encode($start));
                } else {
                    $this->emit('message_start', $this->start);
                }
            }

            $this->offset = $this->nextIndex;
        }

        foreach ($this->seams as $seam) {
            $block = ['type' => 'fallback', 'from' => ['model' => $seam['from']], 'to' => ['model' => $seam['to']], 'trigger' => ['type' => 'refusal', 'category' => $seam['category']]];
            $this->emit('content_block_start', self::encode((object) ['type' => 'content_block_start', 'index' => $this->nextIndex, 'content_block' => $block]));
            $this->emit('content_block_stop', self::encode((object) ['type' => 'content_block_stop', 'index' => $this->nextIndex]));
            $this->nextIndex++;
            $this->offset = $this->nextIndex;
        }

        $this->seams = [];
    }

    /**
     * The fallback's final `message_delta` with every attempt in `usage.iterations`, and the `input_transformations` of
     * the start that wasn't sent.
     */
    private function stitched(stdClass $event, bool $refused): stdClass
    {
        self::putIterations($event, [...$this->ledger, ...$this->iterations($event, true)]);

        if ($this->startSuppressed) {
            self::forwardTransformations($event, $this->start);
        }

        $details = ($event->delta ?? null) instanceof stdClass ? ($event->delta->stop_details ?? null) : null;

        if ($refused && $details instanceof stdClass && ! property_exists($details, 'recommended_model')) {
            $details->recommended_model = null;
        }

        return $event;
    }

    /**
     * One answer's `usage.iterations` entries: its own when it reports them, else one from its usage; on the answer
     * that served, the last is `fallback_message`, and every entry names its model.
     *
     * @return list<array<array-key, mixed>>
     */
    private function iterations(stdClass $event, bool $served): array
    {
        $usage = json_decode(self::encode($event->usage ?? new stdClass), true);
        $usage = is_array($usage) ? $usage : [];
        $entries = array_values(array_filter(is_array($usage['iterations'] ?? null) ? $usage['iterations'] : [], is_array(...)));

        if ($entries === []) {
            $entries = [array_filter([
                'type' => 'message',
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'cache_read_input_tokens' => $usage['cache_read_input_tokens'] ?? 0,
                'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
                'cache_creation' => $usage['cache_creation'] ?? null,
            ], fn (mixed $value): bool => $value !== null)];
        }

        $last = count($entries) - 1;

        foreach ($entries as $i => $entry) {
            $type = $entry['type'] ?? null;

            if ($served && $i === $last) {
                $type = 'fallback_message';
            } elseif (! is_string($type) || $type === '' || $type === 'fallback_message') {
                $type = 'message';
            }

            $entry['type'] = $type;
            // The answer that served is named as the API resolved it, as its `fallback` block is.
            $entry['model'] ??= $served ? $this->served : $this->model;
            $entries[$i] = $entry;
        }

        return $entries;
    }

    /**
     * The refused partial a fallback carries on from: the answer's blocks after its last `fallback` block, tool inputs
     * decoded, an empty one as `{}`, and the last text without trailing whitespace, which the API refuses at the end of
     * an assistant turn. A client `tool_use` goes, as the fallback credit guide says: nothing answers it.
     *
     * @return list<stdClass>
     */
    private function claim(): array
    {
        $claim = [];
        ksort($this->blocks);

        foreach ($this->blocks as $index => $block) {
            if (($block->type ?? null) === 'fallback') {
                $claim = [];

                continue;
            }

            if (($block->type ?? null) === 'tool_use') {
                continue;
            }

            // Decoded as objects, so an empty input stays `{}`.
            $block = clone $block;
            $input = isset($this->inputs[$index]) ? json_decode($this->inputs[$index]) : null;

            if ($input !== null) {
                $block->input = $input;
            }

            $claim[] = $block;
        }

        $last = end($claim);

        if ($last instanceof stdClass && ($last->type ?? null) === 'text' && is_string($last->text ?? null)) {
            $last->text = rtrim($last->text, " \t\r\n");
        }

        return $claim;
    }

    /**
     * Reads the refused answer's `message_stop`, past pings; one is made when it doesn't come.
     */
    private function stopAhead(): string
    {
        while (($event = $this->nextEvent()) !== null) {
            if ($event[0] !== 'ping') {
                return $event[0] === 'message_stop' ? $event[1] : '{"type":"message_stop"}';
            }
        }

        return '{"type":"message_stop"}';
    }

    /**
     * The next entry to ask, past every model that already refused; null at the end of the chain.
     */
    private function nextEntry(): ?int
    {
        for ($index = $this->next; $index < count($this->chain); $index++) {
            $model = $this->chain[$index]['model'] ?? null;

            if (! is_string($model) || ! isset($this->refused[$model])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The current answer's next event: its name and its data, the `data:` lines joined; null at its end.
     *
     * @return array{0: string, 1: string}|null
     */
    private function nextEvent(): ?array
    {
        $name = '';
        $data = [];

        while (($line = $this->line()) !== null) {
            if ($line === '') {
                if ($data === [] && $name === '') {
                    continue;
                }

                return self::event($name, $data);
            }

            if (str_starts_with($line, 'event:')) {
                $name = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $value = substr($line, 5);
                $data[] = str_starts_with($value, ' ') ? substr($value, 1) : $value;
            }
        }

        // An event the stream ended in the middle of still counts, as the client's own reader dispatches it.
        return $data === [] ? null : self::event($name, $data);
    }

    /**
     * An event from its name and its `data:` lines, joined; without an `event:` line, it's named by its data's `type`.
     *
     * @param  list<string>  $lines
     * @return array{0: string, 1: string}
     */
    private static function event(string $name, array $lines): array
    {
        $data = implode("\n", $lines);

        if ($name === '') {
            $type = self::decode($data)->type ?? null;
            $name = is_string($type) ? $type : 'message';
        }

        return [$name, $data];
    }

    private function line(): ?string
    {
        while (($position = strpos($this->raw, "\n")) === false) {
            if ($this->upstreamEnded || $this->body->eof()) {
                $this->upstreamEnded = true;

                if ($this->raw === '') {
                    return null;
                }

                $line = $this->raw;
                $this->raw = '';

                return rtrim($line, "\r");
            }

            $this->raw .= $this->body->read(8192);
        }

        $line = substr($this->raw, 0, $position);
        $this->raw = substr($this->raw, $position + 1);

        return rtrim($line, "\r");
    }

    /**
     * Sets a `message_delta`'s `usage.iterations`, its other usage kept.
     *
     * @param  list<array<array-key, mixed>>  $iterations
     */
    private static function putIterations(stdClass $event, array $iterations): void
    {
        $usage = ($event->usage ?? null) instanceof stdClass ? $event->usage : new stdClass;
        $usage->iterations = $iterations;
        $event->usage = $usage;
    }

    /**
     * Puts a start's `input_transformations` on a `message_delta` that has none, when that start wasn't sent.
     */
    private static function forwardTransformations(stdClass $event, ?string $start): void
    {
        $message = $start === null ? null : (self::decode($start)->message ?? null);

        if ($message instanceof stdClass && property_exists($message, 'input_transformations') && ! property_exists($event, 'input_transformations')) {
            $event->input_transformations = $message->input_transformations;
        }
    }

    private function emit(string $type, string $data): void
    {
        $this->out .= "event: {$type}\ndata: ".str_replace("\n", "\ndata: ", $data)."\n\n";
    }

    private static function decode(string $data): ?stdClass
    {
        $decoded = json_decode($data);

        return $decoded instanceof stdClass ? $decoded : null;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function append(stdClass $block, string $field, mixed $text): void
    {
        if (! is_string($block->{$field} ?? null)) {
            $block->{$field} = '';
        }

        $block->{$field} .= is_string($text) ? $text : '';
    }

    /**
     * Only the keys the delta sends: a replayed compaction block with one more is refused.
     */
    private static function compact(stdClass $block, stdClass $delta): void
    {
        if (is_string($delta->content ?? null)) {
            self::append($block, 'content', $delta->content);
        }

        if (property_exists($delta, 'encrypted_content')) {
            $block->encrypted_content = $delta->encrypted_content;
        }
    }
}
