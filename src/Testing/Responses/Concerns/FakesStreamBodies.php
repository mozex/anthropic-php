<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\ResponseInterface;

/**
 * What the stream fakes share: the PSR-7 response around their body, with headers `meta()` reads.
 */
trait FakesStreamBodies
{
    /**
     * @param  resource|string  $body
     * @param  array<string, string|list<string>>  $headers
     */
    private static function fakeHttpResponse($body, array $headers): ResponseInterface
    {
        $streams = Psr17FactoryDiscovery::findStreamFactory();
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse()
            ->withBody(is_string($body) ? $streams->createStream($body) : $streams->createStreamFromResource($body));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * The headers of the other fakes' meta: their rate limits and request ID.
     *
     * @return array<string, string>
     */
    private static function fakeMetaHeaders(): array
    {
        return [
            'anthropic-ratelimit-requests-limit' => '5',
            'anthropic-ratelimit-requests-remaining' => '4',
            'anthropic-ratelimit-requests-reset' => '2024-04-30T15:56:17Z',
            'anthropic-ratelimit-tokens-limit' => '25000',
            'anthropic-ratelimit-tokens-remaining' => '25000',
            'anthropic-ratelimit-tokens-reset' => '2024-04-30T15:56:17Z',
            'request-id' => '02c10373f63cf2954851197d75c0adab',
        ];
    }
}
