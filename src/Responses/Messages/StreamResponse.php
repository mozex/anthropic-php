<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Responses\Concerns\ReadsStreams;
use Anthropic\Responses\Meta\MetaInformation;
use Closure;
use Generator;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @template TResponse
 *
 * @implements ResponseStreamContract<TResponse>
 */
class StreamResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
{
    use ReadsStreams;

    /**
     * Not readonly, so a copy gets its own (`__clone`).
     */
    private MessageAccumulator $accumulator;

    /**
     * @var array{event: list<Closure(TResponse): void>, text: list<Closure(string): void>, thinking: list<Closure(string): void>, contentBlock: list<Closure(CreateResponseContent): void>, message: list<Closure(CreateResponse): void>}
     */
    private array $callbacks = ['event' => [], 'text' => [], 'thinking' => [], 'contentBlock' => [], 'message' => []];

    private bool $reported = false;

    /**
     * While a callback runs, which can't read the stream it's called from.
     */
    private bool $firing = false;

    private readonly int $startedAt;

    /**
     * Creates a new Stream Response instance.
     *
     * @param  class-string<TResponse>  $responseClass
     * @param  (Closure(self<TResponse>): void)|null  $onComplete  called once, when the stream ends, fails, or is dropped
     */
    public function __construct(
        private readonly string $responseClass,
        private readonly ResponseInterface $response,
        private readonly ?Closure $onComplete = null,
    ) {
        $this->startedAt = hrtime(true);
        $this->accumulator = new MessageAccumulator;
    }

    /**
     * This stream as a class that extends it, over the same response and with its callbacks, for a framework that adds
     * its own methods. The copy reports the call; this one then reports nothing.
     *
     * @template TStream of self
     *
     * @param  class-string<TStream>  $class
     * @return TStream
     *
     * @throws LogicException once the stream was read, since the copy reads from where the response stands
     */
    public function as(string $class): self
    {
        if ($this->accumulator->toArray() !== null) {
            throw new LogicException('Call as() before reading the stream.');
        }

        $copy = new $class($this->responseClass, $this->response, $this->onComplete);
        $copy->callbacks = $this->callbacks;
        $copy->reported = $this->reported;
        $this->reported = true;

        return $copy;
    }

    /**
     * {@inheritDoc}
     *
     * @return Generator<int, TResponse>
     */
    public function getIterator(): Generator
    {
        if ($this->firing) {
            throw new LogicException("A stream's callback can't read the stream: call currentMessage() for the message so far.");
        }

        foreach ($this->readEvents($this->response->getBody()) as $data) {
            /** @var array{type: string, error?: string|array{type: ?string, message: string|array<int, string>, details?: mixed}, request_id?: ?string, message?: array{model?: string, usage?: array<string, mixed>}, usage?: array<string, mixed>} $response */
            $response = json_decode(trim($data), true, flags: JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                $error = is_string($response['error']) ? ['message' => $response['error']] : $response['error'];

                if (is_string($response['request_id'] ?? null)) {
                    $error['request_id'] = $response['request_id'];
                }

                $exception = ErrorException::forType($error, $this->response);

                try {
                    $this->report();
                } catch (Throwable) {
                    // Dropped, so a catch for the API's error still runs.
                }

                throw $exception;
            }

            $this->accumulator->add($response);

            if ($response['type'] === 'message_stop') {
                $this->report();
                $this->fire('message', fn (): ?CreateResponse => $this->accumulator->message($this->meta()));

                break;
            }

            if ($response['type'] === 'ping') {
                continue;
            }

            $event = $this->responseClass::from($response);
            $this->fire('event', fn () => $event);
            $this->fireFor($response);

            yield $event;
        }
    }

    /**
     * The text as it streams: each `text_delta`, read from where the stream stands.
     *
     * @return Generator<int, string>
     */
    public function text(): Generator
    {
        foreach ($this as $event) {
            if ($event instanceof CreateStreamedResponse && $event->delta->type === 'text_delta') {
                yield (string) $event->delta->text;
            }
        }
    }

    /**
     * The whole message, as the API would have sent it without streaming: the rest of the stream is read first. A
     * stream that ended before `message_stop` gives what it had (`completed()` says so).
     *
     * @throws LogicException when the stream had no `message_start`
     */
    public function finalMessage(): CreateResponse
    {
        foreach ($this as $event) {
            // read to the end
        }

        return $this->accumulator->message($this->meta())
            ?? throw new LogicException('The stream ended without a message_start, so it holds no message.');
    }

    /**
     * The final message's text blocks, joined as they are (a block split at a citation keeps its own spacing); an
     * empty string when it has none.
     */
    public function finalText(): string
    {
        $text = '';

        foreach ($this->finalMessage()->content as $block) {
            $text .= $block->type === 'text' ? (string) $block->text : '';
        }

        return $text;
    }

    /**
     * The message so far, null before `message_start`: mid-stream, its stop reason is null and an open tool call's
     * input is what its JSON decodes to so far, else the start's `{}`.
     */
    public function currentMessage(): ?CreateResponse
    {
        return $this->accumulator->message($this->meta());
    }

    /**
     * Calls the callback with each event read after this, before the loop gets it (`ping` and `message_stop` aren't
     * passed on).
     *
     * @param  Closure(TResponse): void  $callback
     * @return $this
     */
    public function onEvent(Closure $callback): static
    {
        $this->callbacks['event'][] = $callback;

        return $this;
    }

    /**
     * Calls the callback with each text delta read after this. `currentMessage()` has the text so far.
     *
     * @param  Closure(string): void  $callback
     * @return $this
     */
    public function onText(Closure $callback): static
    {
        $this->callbacks['text'][] = $callback;

        return $this;
    }

    /**
     * Calls the callback with each thinking delta read after this.
     *
     * @param  Closure(string): void  $callback
     * @return $this
     */
    public function onThinking(Closure $callback): static
    {
        $this->callbacks['thinking'][] = $callback;

        return $this;
    }

    /**
     * Calls the callback with each block once it stops, whole: a tool call with its input decoded.
     *
     * @param  Closure(CreateResponseContent): void  $callback
     * @return $this
     */
    public function onContentBlock(Closure $callback): static
    {
        $this->callbacks['contentBlock'][] = $callback;

        return $this;
    }

    /**
     * Calls the callback with the whole message at `message_stop`.
     *
     * @param  Closure(CreateResponse): void  $callback
     * @return $this
     */
    public function onMessage(Closure $callback): static
    {
        $this->callbacks['message'][] = $callback;

        return $this;
    }

    /**
     * The usage the stream reported so far: the input counts from `message_start`, the output (and anything else the
     * API sends there) from the last `message_delta`.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->accumulator->usage();
    }

    /**
     * The model that answered: `message_start`'s, or after a server-side fallback the one the `fallback` block hands
     * to and the final `usage.iterations` names; null before `message_start` is read.
     */
    public function model(): ?string
    {
        return $this->accumulator->model();
    }

    /**
     * Whether the stream was read to its `message_stop`: false when it failed or was dropped before the end, even right
     * after its last `message_delta`.
     */
    public function completed(): bool
    {
        return $this->accumulator->completed();
    }

    /**
     * The milliseconds from the response's headers to now.
     */
    public function durationMs(): int
    {
        return intdiv(hrtime(true) - $this->startedAt, 1_000_000);
    }

    /**
     * A copy reports nothing of its own: it's the same API call.
     */
    public function __clone()
    {
        $this->reported = true;
        $this->accumulator = clone $this->accumulator;
    }

    /**
     * A stream dropped before its end (a `break`, an exception in the loop, or never read) reports what it has.
     */
    public function __destruct()
    {
        try {
            $this->report();
        } catch (Throwable) {
            // A destructor can't fail the code that dropped the stream.
        }
    }

    /**
     * Calls the callbacks of a kind, building what they get only when there are some, and none for a block or message
     * that isn't there.
     *
     * @param  'event'|'text'|'thinking'|'contentBlock'|'message'  $kind
     * @param  Closure(): mixed  $value
     */
    private function fire(string $kind, Closure $value): void
    {
        if ($this->callbacks[$kind] === [] || ($value = $value()) === null) {
            return;
        }

        $this->firing = true;

        try {
            foreach ($this->callbacks[$kind] as $callback) {
                $callback($value);
            }
        } finally {
            $this->firing = false;
        }
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function fireFor(array $event): void
    {
        $delta = is_array($event['delta'] ?? null) ? $event['delta'] : [];

        match (true) {
            $event['type'] === 'content_block_delta' && ($delta['type'] ?? null) === 'text_delta' => $this->fire('text', fn (): string => is_string($delta['text'] ?? null) ? $delta['text'] : ''),
            $event['type'] === 'content_block_delta' && ($delta['type'] ?? null) === 'thinking_delta' => $this->fire('thinking', fn (): string => is_string($delta['thinking'] ?? null) ? $delta['thinking'] : ''),
            $event['type'] === 'content_block_stop' && is_int($event['index'] ?? null) => $this->fire('contentBlock', fn (): ?CreateResponseContent => $this->accumulator->message()?->content[$event['index']] ?? null),
            default => null,
        };
    }

    private function report(): void
    {
        if ($this->reported || $this->onComplete === null) {
            return;
        }

        $this->reported = true;
        ($this->onComplete)($this);
    }

    public function meta(): MetaInformation
    {
        return MetaInformation::from($this->response->getHeaders());
    }
}
