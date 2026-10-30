<?php

declare(strict_types=1);

namespace Anthropic\Testing;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Records a test's API calls once, then replays them: a client middleware (`Factory::withMiddleware()`) that, while
 * its cassette file is missing or `$record` is true, sends each request and saves the answer, and otherwise answers
 * every request from the file in order, with no HTTP call. A cassette holds each request's method and path and each
 * answer's status, its safe headers and its body, never a request's headers or body, so no key or prompt is saved.
 */
class Recorder
{
    /**
     * The answer headers a cassette keeps: what the client and `meta()` read, and nothing that names the account (its
     * organization or workspace).
     */
    private const KEPT_HEADER = '/^(content-type|request-id|retry-after(-ms)?|x-should-retry|anthropic-(?!organization-id|workspace-id).+)$/i';

    /**
     * How a body and the cassette are encoded: the same flags for both, so JSON read back from the file encodes to the
     * bytes it was recorded from (`1.0` stays `1.0`).
     */
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * The interactions recorded or left to replay.
     *
     * @var list<array{request: array{method: string, path: string}, response: array{status: int, headers: array<string, string>, body?: string, json?: mixed, base64?: string}}>
     */
    private array $interactions = [];

    private int $replayed = 0;

    private readonly bool $recording;

    /**
     * The cassette a recording replaces, put back if the API refuses the key.
     */
    private readonly ?string $previous;

    public function __construct(private readonly string $path, ?bool $record = null)
    {
        $this->recording = $record ?? ! is_file($path);
        $this->previous = $this->recording && is_file($path) ? (string) file_get_contents($path) : null;

        if (! $this->recording && ! is_file($path)) {
            throw new RuntimeException("The cassette [{$path}] doesn't exist, and recording is off: record it first.");
        }

        if (! $this->recording) {
            $this->interactions = self::read($path);
        }
    }

    /**
     * The recorder for a cassette file: recording while it's missing or `$record` is true, replaying otherwise.
     */
    public static function cassette(string $path, ?bool $record = null): self
    {
        return new self($path, $record);
    }

    public function recording(): bool
    {
        return $this->recording;
    }

    /**
     * @param  Closure(RequestInterface): ResponseInterface  $next
     */
    public function __invoke(RequestInterface $request, Closure $next): ResponseInterface
    {
        return $this->recording ? $this->record($request, $next) : $this->replay($request);
    }

    /**
     * @param  Closure(RequestInterface): ResponseInterface  $next
     */
    private function record(RequestInterface $request, Closure $next): ResponseInterface
    {
        $response = $next($request);

        // A refused key would replay as a refusal from then on, so the cassette goes back to what it was.
        if ($response->getStatusCode() === 401) {
            $this->discard();

            throw new RuntimeException("The API refused the key (status 401) while recording [{$this->path}]: set a valid key and run the test again. The cassette was left as it was.");
        }

        // A stream is read whole and handed on as text, so the caller still reads every event.
        $body = (string) $response->getBody();
        $response = $response->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($body));

        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            if (preg_match(self::KEPT_HEADER, (string) $name) === 1) {
                $headers[strtolower((string) $name)] = implode(', ', $values);
            }
        }

        // A JSON answer that encodes back to the same bytes is kept as JSON, objects as objects, so the file reads well
        // and `{}` replays as `{}`; any other text as it came, and a body that isn't UTF-8, such as a downloaded file, as
        // base64.
        $json = str_contains(strtolower($response->getHeaderLine('Content-Type')), 'json') ? json_decode($body) : null;
        $kept = match (true) {
            $json !== null && self::encode($json) === $body => ['json' => $json],
            preg_match('//u', $body) === 1 => ['body' => $body],
            default => ['base64' => base64_encode($body)],
        };

        $this->interactions[] = [
            'request' => ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath()],
            'response' => ['status' => $response->getStatusCode(), 'headers' => $headers, ...$kept],
        ];

        $this->write();

        return $response;
    }

    private function replay(RequestInterface $request): ResponseInterface
    {
        $index = $this->replayed++;
        $interaction = $this->interactions[$index] ?? null;
        $sent = $request->getMethod().' '.$request->getUri()->getPath();

        if ($interaction === null) {
            throw new RuntimeException("The cassette [{$this->path}] holds ".count($this->interactions)." requests, and the test sent another: {$sent}. Record it again.");
        }

        $recorded = $interaction['request']['method'].' '.$interaction['request']['path'];

        if ($recorded !== $sent) {
            throw new RuntimeException('Request '.($index + 1)." of the cassette [{$this->path}] is {$recorded}, but the test sent {$sent}. Record it again.");
        }

        $answer = $interaction['response'];
        $body = match (true) {
            array_key_exists('json', $answer) => self::encode($answer['json']),
            array_key_exists('base64', $answer) => (string) base64_decode($answer['base64'], true),
            default => $answer['body'] ?? '',
        };
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse($answer['status'])
            ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($body));

        foreach ($answer['headers'] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private static function encode(mixed $json): string
    {
        return (string) json_encode($json, self::JSON_FLAGS);
    }

    /**
     * Puts back the cassette this recording replaced, or removes the one it started.
     */
    private function discard(): void
    {
        if ($this->interactions === []) {
            return;
        }

        if ($this->previous === null) {
            @unlink($this->path);
        } else {
            file_put_contents($this->path, $this->previous);
        }

        $this->interactions = [];
    }

    private function write(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("The cassette's directory [{$directory}] can't be created.");
        }

        $contents = json_encode(['interactions' => $this->interactions], self::JSON_FLAGS | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";

        // Written aside and moved into place, so a failed or interrupted write never leaves half a cassette.
        $temporary = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new RuntimeException("The cassette [{$this->path}] can't be written.");
        }
    }

    /**
     * @return list<array{request: array{method: string, path: string}, response: array{status: int, headers: array<string, string>, body?: string, json?: mixed, base64?: string}}>
     */
    private static function read(string $path): array
    {
        $cassette = json_decode((string) file_get_contents($path), false);
        $interactions = is_object($cassette) && is_array($cassette->interactions ?? null) ? $cassette->interactions : null;

        if ($interactions === null || array_filter($interactions, fn (mixed $interaction): bool => ! self::wrote($interaction)) !== []) {
            throw new RuntimeException("The cassette [{$path}] isn't one the recorder wrote. Delete it, or record it again.");
        }

        return array_values(array_map(fn (object $interaction): array => [
            'request' => (array) $interaction->request,
            'response' => [
                'status' => $interaction->response->status,
                'headers' => (array) $interaction->response->headers,
                ...match (true) {
                    property_exists($interaction->response, 'json') => ['json' => $interaction->response->json],
                    property_exists($interaction->response, 'base64') => ['base64' => (string) $interaction->response->base64],
                    default => ['body' => (string) ($interaction->response->body ?? '')],
                },
            ],
        ], $interactions));
    }

    /**
     * Whether an interaction has the shape the recorder writes, so a hand-edited cassette fails here, not mid-test.
     */
    private static function wrote(mixed $interaction): bool
    {
        if (! is_object($interaction)) {
            return false;
        }

        $request = $interaction->request ?? null;
        $response = $interaction->response ?? null;

        return is_object($request) && is_string($request->method ?? null) && is_string($request->path ?? null)
            && is_object($response) && is_int($response->status ?? null)
            && (is_object($response->headers ?? null) || ($response->headers ?? null) === [])
            && array_filter((array) ($response->headers ?? []), fn (mixed $value): bool => ! is_string($value)) === []
            && (! property_exists($response, 'body') || is_string($response->body))
            && (! property_exists($response, 'base64') || (is_string($response->base64) && base64_decode($response->base64, true) !== false));
    }
}
