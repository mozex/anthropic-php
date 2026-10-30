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
     * The usage so far: `message_start`'s, with each `message_delta`'s cumulative counts over it.
     *
     * @var array<string, mixed>
     */
    private array $usage = [];

    private ?string $model = null;

    private bool $completed = false;

    private bool $reported = false;

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
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Generator
    {
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

            $this->fold($response);

            if ($response['type'] === 'message_stop') {
                $this->completed = true;
                $this->report();

                break;
            }

            if ($response['type'] === 'ping') {
                continue;
            }

            yield $this->responseClass::from($response);
        }
    }

    /**
     * The usage the stream reported so far: the input counts from `message_start`, the output (and anything else the
     * API sends there) from the last `message_delta`.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->usage;
    }

    /**
     * The model that answered: `message_start`'s, or after a server-side fallback the one the `fallback` block hands
     * to and the final `usage.iterations` names; null before `message_start` is read.
     */
    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Whether the stream was read to its `message_stop`: false when it failed or was dropped before the end, even right
     * after its last `message_delta`.
     */
    public function completed(): bool
    {
        return $this->completed;
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
     * @param  array{type: string, message?: array{model?: string, usage?: array<string, mixed>}, usage?: array<string, mixed>, content_block?: mixed}  $event
     */
    private function fold(array $event): void
    {
        if ($event['type'] === 'message_start') {
            $this->model = is_string($event['message']['model'] ?? null) ? $event['message']['model'] : null;
            $this->usage = is_array($event['message']['usage'] ?? null) ? $event['message']['usage'] : [];
        }

        // A fallback mid-output: message_start named the model that declined, this block the one that answers.
        $block = $event['type'] === 'content_block_start' && is_array($event['content_block'] ?? null) ? $event['content_block'] : [];

        if (($block['type'] ?? null) === 'fallback' && is_array($block['to'] ?? null) && is_string($block['to']['model'] ?? null)) {
            $this->model = $block['to']['model'];
        }

        if ($event['type'] === 'message_delta' && is_array($event['usage'] ?? null)) {
            $this->usage = array_replace($this->usage, array_filter($event['usage'], fn (mixed $value): bool => $value !== null));

            foreach (is_array($this->usage['iterations'] ?? null) ? $this->usage['iterations'] : [] as $iteration) {
                if (is_array($iteration) && ($iteration['type'] ?? null) === 'fallback_message' && is_string($iteration['model'] ?? null)) {
                    $this->model = $iteration['model'];
                }
            }
        }
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
