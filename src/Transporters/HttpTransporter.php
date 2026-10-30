<?php

declare(strict_types=1);

namespace Anthropic\Transporters;

use Anthropic\Contracts\TransporterContract;
use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\TimeoutException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Anthropic\ValueObjects\Transporter\RequestOptions;
use Anthropic\ValueObjects\Transporter\Response;
use Closure;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
class HttpTransporter implements TransporterContract
{
    /**
     * Creates a new Http Transporter instance.
     *
     * @param  Closure(RequestInterface): ResponseInterface  $streamHandler
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly BaseUri $baseUri,
        private Headers $headers,
        private readonly QueryParams $queryParams,
        private readonly Closure $streamHandler,
    ) {
        // ..
    }

    /**
     * {@inheritDoc}
     */
    public function requestObject(Payload $payload): Response
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn (): ResponseInterface => $this->client->sendRequest($request));

        $contents = (string) $response->getBody();

        $this->throwIfError($response, $contents);

        if (str_contains($response->getHeaderLine('Content-Type'), ContentType::TEXT_PLAIN->value)) {
            return Response::from($contents, $response->getHeaders());
        }

        try {
            /** @var array{error?: array{message: string, type: string}} $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new UnserializableResponse($jsonException, $response);
        }

        return Response::from($data, $response->getHeaders());
    }

    /**
     * {@inheritDoc}
     */
    public function requestContent(Payload $payload): string
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn (): ResponseInterface => $this->client->sendRequest($request));

        $contents = (string) $response->getBody();

        $this->throwIfError($response, $contents);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function requestStream(Payload $payload): ResponseInterface
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn () => ($this->streamHandler)($request));

        $this->throwIfError($response);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers = $this->headers->withCustomHeader($name, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withOptions(array $options): self
    {
        $options = RequestOptions::from($options);

        return new self(
            $this->client,
            $this->baseUri,
            $options->applyToHeaders($this->headers),
            $options->applyToQueryParams($this->queryParams),
            $this->streamHandler,
        );
    }

    /**
     * @param  Closure(): ResponseInterface  $callable
     */
    private function sendRequest(Closure $callable): ResponseInterface
    {
        try {
            return $callable();
        } catch (ClientExceptionInterface $clientException) {
            // Guzzle throws on 4xx and 5xx when it streams; the response still carries the API error.
            if ($clientException instanceof BadResponseException) {
                $this->throwIfError($clientException->getResponse());
            }

            throw match (true) {
                $this->isTimeout($clientException) => new TimeoutException($clientException),
                // Guzzle reports a cURL failure without a response (a reset connection, a TLS error) as a RequestException.
                $clientException instanceof NetworkExceptionInterface,
                $clientException instanceof RequestException && ! $clientException->hasResponse() => new ConnectionException($clientException),
                default => new TransporterException($clientException),
            };
        }
    }

    /**
     * Whether the HTTP client gave up waiting: cURL says "timed out", Symfony's client "Idle timeout" or "Max duration".
     */
    private function isTimeout(ClientExceptionInterface $exception): bool
    {
        $message = $exception->getMessage();

        // The message ends with the request's URL, which may itself contain "timeout".
        if ($exception instanceof NetworkExceptionInterface || $exception instanceof RequestExceptionInterface) {
            $uri = $exception->getRequest()->getUri();
            $message = str_replace([(string) $uri, (string) $uri->withUserInfo('')], '', $message);
        }

        return preg_match('/timed? ?out|max duration/i', $message) === 1;
    }

    /**
     * Throws for every status of 400 and above: with the API's error when the body carries one,
     * otherwise with the first line of the body's text.
     */
    private function throwIfError(ResponseInterface $response, ?string $contents = null): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        $contents ??= (string) $response->getBody();

        try {
            /** @var mixed $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        $error = is_array($data) ? $data['error'] ?? null : null;

        /** @var array{message?: string|array<int, string>, type?: ?string, details?: mixed, request_id?: ?string}|null $error */
        $error = match (true) {
            is_string($error) => ['message' => $error],
            is_array($error) => $error,
            default => null,
        };

        // The body's request_id sits beside the error object, not inside it.
        if ($error !== null && is_array($data) && is_string($data['request_id'] ?? null)) {
            $error['request_id'] = $data['request_id'];
        }

        throw ErrorException::forStatus($error ?? $this->firstLine($contents) ?? $response->getReasonPhrase(), $response);
    }

    /**
     * The first line of text in a body that isn't an API error, such as a proxy's HTML error page.
     */
    private function firstLine(string $contents): ?string
    {
        // ASCII line endings only: \R would also match the 0x85 byte inside a UTF-8 character.
        foreach (preg_split('/\r\n|\r|\n/', strip_tags($contents)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                return preg_match('/^.{1,200}/us', $line, $matches) === 1 ? $matches[0] : substr($line, 0, 200);
            }
        }

        return null;
    }
}
