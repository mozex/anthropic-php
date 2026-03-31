<?php

declare(strict_types=1);

namespace Anthropic\Transporters;

use Anthropic\Contracts\TransporterContract;
use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Anthropic\ValueObjects\Transporter\Response;
use Closure;
use GuzzleHttp\Exception\ClientException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class HttpTransporter implements TransporterContract
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

        if (str_contains($response->getHeaderLine('Content-Type'), ContentType::TEXT_PLAIN->value)) {
            return Response::from($contents, $response->getHeaders());
        }

        $this->throwIfJsonError($response, $contents);

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

        $this->throwIfJsonError($response, $contents);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function requestStream(Payload $payload): ResponseInterface
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn () => ($this->streamHandler)($request));

        $this->throwIfJsonError($response, $response);

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
     * @param  Closure(): ResponseInterface  $callable
     */
    private function sendRequest(Closure $callable): ResponseInterface
    {
        try {
            return $callable();
        } catch (ClientExceptionInterface $clientException) {
            if ($clientException instanceof ClientException) {
                $this->throwIfJsonError($clientException->getResponse(), (string) $clientException->getResponse()->getBody());
            }

            throw new TransporterException($clientException);
        }
    }

    private function throwIfJsonError(ResponseInterface $response, string|ResponseInterface $contents): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        if ($contents instanceof ResponseInterface) {
            $contents = (string) $contents->getBody();
        }

        try {
            /** @var array{error?: array{message: string|array<int, string>, type: string}} $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            if (isset($data['error'])) {
                if ($response->getStatusCode() === 429) {
                    throw new RateLimitException($data['error'], $response);
                }

                throw new ErrorException($data['error'], $response);
            }

            if ($response->getStatusCode() === 429) {
                throw new RateLimitException([], $response);
            }
        } catch (JsonException $jsonException) {
            if ($response->getStatusCode() === 429) {
                throw new RateLimitException([], $response);
            }

            if (! str_contains($response->getHeaderLine('Content-Type'), ContentType::JSON->value)) {
                return;
            }

            throw new UnserializableResponse($jsonException, $response);
        }
    }
}
