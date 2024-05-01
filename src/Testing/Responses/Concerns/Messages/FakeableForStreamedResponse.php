<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Messages;

use Anthropic\Responses\Messages\StreamResponse;
use Http\Discovery\Psr17FactoryDiscovery;

trait FakeableForStreamedResponse
{
    /**
     * @param  resource  $resource
     */
    public static function fake($resource = null): StreamResponse
    {
        if ($resource === null) {
            $filename = str_replace(['Anthropic\Responses', '\\'], [__DIR__.'/../../Fixtures/', '/'], static::class).'Fixture.txt';
            $resource = fopen($filename, 'r');
        }

        $stream = Psr17FactoryDiscovery::findStreamFactory()
            ->createStreamFromResource($resource);

        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse()
            ->withBody($stream);

        return new StreamResponse(static::class, $response);
    }
}
