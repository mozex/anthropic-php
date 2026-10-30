<?php

declare(strict_types=1);

namespace Anthropic\Middleware;

use Anthropic\Exceptions\InvalidArgumentException;
use Closure;
use Http\Discovery\Psr17Factory;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Retries a refused Messages request down a chain of fallback models, as the official SDKs' middleware does:
 *
 * ```php
 * Anthropic::factory()->withMiddleware(new RefusalFallbackMiddleware(['claude-opus-4-8'], state: new FallbackState));
 * ```
 *
 * On `stop_reason: "refusal"`, the next entry's `model` (and its `max_tokens`, `thinking`, `output_config` or `speed`
 * overrides) is merged over the request, and the refusal's `fallback_credit_token` rides along in `best_effort` mode,
 * so the retry isn't billed for the new model's cache writes. The `fallback-credit-2026-07-01` beta goes on every
 * request the middleware handles. The answer gains a `fallback` block at each model boundary and the attempts in
 * `usage.iterations`, as a server-side fallback answers; the blocks it puts at the front of a turn are taken out of the
 * history again on later requests. A `FallbackState` shared by a conversation's requests pins them to the model that
 * answered.
 *
 * A request with `speed: fast` that falls back to an entry without `speed`, and gets a 429 or 529 there, is sent once
 * more without `speed`. Streamed requests, Message Batches and token counting pass through untouched, as does a request
 * with the server-side `fallback`; one with `fallbacks` throws, since the API would run its own chain.
 */
class RefusalFallbackMiddleware
{
    public const BETA = 'fallback-credit-2026-07-01';

    /**
     * The entry keys merged into a retry; others are dropped.
     */
    private const OVERRIDES = ['model', 'max_tokens', 'thinking', 'output_config', 'speed'];

    /**
     * @var list<array<string, mixed>>
     */
    private readonly array $chain;

    /**
     * Called before each retry with the refused message, the entry and its index.
     *
     * @var (Closure(array<string, mixed>, array<string, mixed>, int): void)|null
     */
    private readonly ?Closure $onFallback;

    /**
     * The arguments come in the official PHP SDK's order.
     *
     * @param  array<int, string|array<string, mixed>>  $fallbacks  model names, or entries with a `model` and overrides of `max_tokens`, `thinking`, `output_config` or `speed`
     * @param  (callable(array<string, mixed>, array<string, mixed>, int): void)|null  $onFallback  called before each retry with the refused message, the entry and its index
     */
    public function __construct(
        array $fallbacks,
        ?callable $onFallback = null,
        private readonly ?FallbackState $state = null,
    ) {
        $chain = [];

        foreach ($fallbacks as $fallback) {
            $entry = is_string($fallback) ? ['model' => $fallback] : $fallback;

            if (! is_string($entry['model'] ?? null) || trim($entry['model']) === '') {
                throw new InvalidArgumentException('Each fallback needs a model.');
            }

            $chain[] = array_intersect_key($entry, array_flip(self::OVERRIDES));
        }

        $this->chain = $chain;
        $this->onFallback = $onFallback === null ? null : $onFallback(...);
    }

    /**
     * @param  Closure(RequestInterface): ResponseInterface  $next
     */
    public function __invoke(RequestInterface $request, Closure $next): ResponseInterface
    {
        if ($this->chain === [] || $request->getMethod() !== 'POST' || ! str_ends_with($request->getUri()->getPath(), '/messages')) {
            return $next($request);
        }

        $body = self::requestBody($request);

        if ($body === null || ($body['stream'] ?? false) === true || isset($body['fallback'])) {
            return $next($request);
        }

        if (isset($body['fallbacks'])) {
            throw new InvalidArgumentException('The `fallbacks` parameter runs the API\'s own fallback chain: send it without RefusalFallbackMiddleware, or leave it out.');
        }

        $request = self::withCreditBeta($request->withHeader('Accept-Encoding', 'identity'));
        $body = self::withoutFallbackBlocks($body);
        $index = $this->startIndex();
        $entry = $this->chain[$index] ?? null;
        $attempt = $entry === null ? $body : self::merge($body, $entry);

        // The first attempt goes out as written; a retry carries the token its own refusal minted, or none.
        unset($body['fallback_credit_token']);

        $response = $entry === null
            ? $next(self::withBody($request, $attempt))
            : $this->sendHop($request, $next, $attempt, $entry)[0];

        [$response, $message] = self::read($response);

        $model = is_string($attempt['model'] ?? null) ? $attempt['model'] : null;
        $from = $model;
        $refused = [];
        $blocks = $iterations = [];

        while ($message !== null && ($message['stop_reason'] ?? null) === 'refusal') {
            $refused[(string) $model] = true;
            $details = is_array($message['stop_details'] ?? null) ? $message['stop_details'] : [];
            $token = $details['fallback_credit_token'] ?? null;
            $token = is_string($token) && $token !== '' ? $token : null;
            $category = $details['category'] ?? null;

            // A model that refused this request is never asked again, so an entry naming one is skipped.
            do {
                $index++;
            } while ($index < count($this->chain) && isset($refused[$this->chain[$index]['model']]));

            if ($index >= count($this->chain)) {
                break;
            }

            $iterations[] = self::iteration($message, 'message', $model);
            $entry = $this->chain[$index];

            if ($this->onFallback !== null) {
                ($this->onFallback)($message, $entry, $index);
            }

            $retry = self::merge($body, $entry);

            if ($token !== null) {
                $retry['fallback_credit_token'] = ['token' => $token, 'mode' => 'best_effort'];
            }

            $response->getBody()->close();
            [$response, $entry] = $this->sendHop($request, $next, $retry, $entry);

            if ($token !== null && $response->getStatusCode() === 400) {
                // A redemption that's only unavailable for now is worth one more try with the same token.
                if (str_contains((string) $response->getBody(), 'redemption temporarily unavailable')) {
                    $response->getBody()->close();
                    // Merged again, so a `speed` dropped on the way stays dropped.
                    [$response, $entry] = $this->sendHop($request, $next, [...self::merge($body, $entry), 'fallback_credit_token' => $retry['fallback_credit_token']], $entry);
                }

                // A token the API won't take: the retry goes out once more without it, the credit forfeited.
                if ($response->getStatusCode() === 400) {
                    $response->getBody()->close();
                    [$response, $entry] = $this->sendHop($request, $next, self::merge($body, $entry), $entry);
                }
            }

            [$response, $message] = self::read($response);

            if ($message === null) {
                // An error on the fallback, or a retry the transporter will make: the pin stays where it was.
                break;
            }

            // The pin moves once the model has answered, refusal or not.
            if ($this->state !== null) {
                $this->state->index = $index;
            }

            $to = is_string($message['model'] ?? null) ? $message['model'] : $entry['model'];
            $blocks[] = ['type' => 'fallback', 'from' => ['model' => $from], 'to' => ['model' => $to], 'trigger' => ['type' => 'refusal', 'category' => $category]];
            $from = $to;
            $model = is_string($entry['model']) ? $entry['model'] : $model;
        }

        return $blocks === [] ? $response : self::stitch($response, $blocks, $iterations, $model);
    }

    /**
     * Sends one attempt, and a fast one that's rate limited or overloaded once more without `speed`, when the entry
     * doesn't ask for fast mode itself: a model without fast mode rejects any `speed`.
     *
     * @param  Closure(RequestInterface): ResponseInterface  $next
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $entry
     * @return array{ResponseInterface, array<string, mixed>} the response, and the entry a later attempt merges
     */
    private function sendHop(RequestInterface $request, Closure $next, array $body, array $entry): array
    {
        $response = $next(self::withBody($request, $body));

        if (array_key_exists('speed', $entry) || ($body['speed'] ?? null) !== 'fast' || ! in_array($response->getStatusCode(), [429, 529], true)) {
            return [$response, $entry];
        }

        $response->getBody()->close();
        unset($body['speed']);

        return [$next(self::withBody($request, $body)), [...$entry, 'speed' => null]];
    }

    /**
     * The chain entry the request starts at: -1 for the requested model, or the pinned entry.
     */
    private function startIndex(): int
    {
        $index = $this->state->index ?? -1;

        if ($index < -1 || $index >= count($this->chain)) {
            throw new InvalidArgumentException("The fallback state points at entry {$index} of a chain of ".count($this->chain).'; was it shared with another chain?');
        }

        return $index;
    }

    /**
     * Merges an entry over the request: a set field replaces it, null removes it, and `output_config` is patched one
     * field at a time. A `between_tools` thinking setting becomes `disabled` unless the entry sets `thinking`, since
     * the fallback model may not take it.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function merge(array $body, array $entry): array
    {
        if (! array_key_exists('thinking', $entry) && ($body['thinking'] ?? null) instanceof stdClass && ($body['thinking']->type ?? null) === 'between_tools') {
            $body['thinking'] = (object) ['type' => 'disabled'];
        }

        foreach ($entry as $key => $value) {
            if ($key === 'output_config' && is_array($value)) {
                $value = array_filter(
                    [...(array) ($body['output_config'] ?? []), ...$value],
                    fn (mixed $field): bool => $field !== null,
                );
                $value = $value === [] ? null : (object) $value;
            }

            if ($value === null) {
                unset($body[$key]);
            } else {
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /**
     * Takes the `fallback` blocks at the front of each assistant turn out of the history: that's where the middleware
     * puts them. One further in, from a server-side fallback in the middle of an answer, stays where the API expects
     * it. A turn left without content goes too.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private static function withoutFallbackBlocks(array $body): array
    {
        if (! is_array($body['messages'] ?? null)) {
            return $body;
        }

        $messages = [];

        foreach ($body['messages'] as $message) {
            if ($message instanceof stdClass && ($message->role ?? null) === 'assistant' && is_array($message->content ?? null)) {
                $content = array_values($message->content);

                while ($content !== [] && $content[0] instanceof stdClass && ($content[0]->type ?? null) === 'fallback') {
                    array_shift($content);
                }

                if ($content === []) {
                    continue;
                }

                $message = clone $message;
                $message->content = $content;
            }

            $messages[] = $message;
        }

        $body['messages'] = $messages;

        return $body;
    }

    /**
     * The response with its body readable again, and the message in it when it's a 200 with a JSON message (null
     * otherwise, a stream included).
     *
     * @return array{ResponseInterface, array<string, mixed>|null}
     */
    private static function read(ResponseInterface $response): array
    {
        if ($response->getStatusCode() !== 200 || str_contains(strtolower($response->getHeaderLine('Content-Type')), 'event-stream')) {
            return [$response, null];
        }

        $raw = (string) $response->getBody();
        $response = $response->withBody((new Psr17Factory)->createStream($raw));
        $message = json_decode($raw, true);

        if (! is_array($message) || ! isset($message['stop_reason'])) {
            return [$response, null];
        }

        /** @var array<string, mixed> $message */
        return [$response, $message];
    }

    /**
     * One `usage.iterations` entry for an attempt.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private static function iteration(array $message, string $type, ?string $model): array
    {
        $usage = is_array($message['usage'] ?? null) ? $message['usage'] : [];

        return array_filter([
            'type' => $type,
            'model' => $model,
            'input_tokens' => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'cache_read_input_tokens' => $usage['cache_read_input_tokens'] ?? null,
            'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Puts the `fallback` blocks in front of the answer's content and the refused attempts in front of its
     * `usage.iterations`, as a server-side fallback answers. Iterations the answer brought (a compaction, the advisor)
     * are kept; without any, the answer is recorded as the `fallback_message` entry. A body that isn't a message passes
     * through.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $iterations
     */
    private static function stitch(ResponseInterface $response, array $blocks, array $iterations, ?string $model): ResponseInterface
    {
        $raw = (string) $response->getBody();

        try {
            $message = json_decode($raw, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response;
        }

        if (! $message instanceof stdClass || ! is_array($message->content ?? null)) {
            return $response;
        }

        $message->content = [...$blocks, ...$message->content];

        $usage = $message->usage ?? null;
        $usage = $usage instanceof stdClass ? $usage : new stdClass;

        if (is_array($usage->iterations ?? null) && $usage->iterations !== []) {
            $usage->iterations = [...$iterations, ...$usage->iterations];
        } else {
            /** @var array<string, mixed> $served */
            $served = (array) json_decode($raw, true);
            $usage->iterations = [...$iterations, self::iteration($served, 'fallback_message', $model)];
        }

        $message->usage = $usage;

        return $response
            ->withBody((new Psr17Factory)->createStream(json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
            ->withoutHeader('Content-Length');
    }

    /**
     * The request's JSON body, its top level an array and every nested object a `stdClass`, so an empty `{}` goes out
     * as `{}` again; null when there's no JSON object to read.
     *
     * @return array<string, mixed>|null
     */
    private static function requestBody(RequestInterface $request): ?array
    {
        $stream = $request->getBody();

        if (! $stream->isSeekable()) {
            return null;
        }

        $stream->rewind();
        $raw = $stream->getContents();
        $stream->rewind();

        $body = json_decode($raw);

        /** @var array<string, mixed>|null */
        return $body instanceof stdClass ? get_object_vars($body) : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function withBody(RequestInterface $request, array $body): RequestInterface
    {
        return $request
            ->withBody((new Psr17Factory)->createStream(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
            ->withoutHeader('Content-Length');
    }

    /**
     * Adds the fallback credit beta to the request's `anthropic-beta`, once.
     */
    private static function withCreditBeta(RequestInterface $request): RequestInterface
    {
        $betas = array_filter(array_map(trim(...), explode(',', $request->getHeaderLine('anthropic-beta'))), fn (string $beta): bool => $beta !== '');

        return $request->withHeader('anthropic-beta', implode(',', array_unique([...$betas, self::BETA])));
    }
}
