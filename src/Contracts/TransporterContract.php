<?php

declare(strict_types=1);

namespace Anthropic\Contracts;

use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\ValueObjects\ClientSettings;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
interface TransporterContract
{
    /**
     * Sends a request to a server.
     *
     * @return Response<array<array-key, mixed>|string>
     *
     * @throws ErrorException|UnserializableResponse|TransporterException|RateLimitException
     */
    public function requestObject(Payload $payload): Response;

    /**
     * Sends a content request to a server.
     *
     * @throws ErrorException|TransporterException|RateLimitException
     */
    public function requestContent(Payload $payload): string;

    /**
     * Sends a request to any path, returning the status, the body (decoded when it's JSON) and the headers.
     *
     * @return Response<array<array-key, mixed>|string>
     *
     * @throws ErrorException|TransporterException|RateLimitException
     */
    public function requestRaw(Payload $payload): Response;

    /**
     * Sends a stream request to a server.
     *
     * @throws ErrorException|RateLimitException
     */
    public function requestStream(Payload $payload): ResponseInterface;

    /**
     * Adds a custom header to the transporter.
     */
    public function addHeader(string $name, string $value): self;

    /**
     * What the transporter sends with every request: the base URI, the API version, the betas, the workspace, the
     * retries and the timeouts the factory set, never a credential.
     */
    public function settings(): ClientSettings;

    /**
     * Returns a transporter whose requests also carry the given options, leaving this one unchanged.
     *
     * @param  array<array-key, mixed>  $options
     */
    public function withOptions(array $options): self;
}
