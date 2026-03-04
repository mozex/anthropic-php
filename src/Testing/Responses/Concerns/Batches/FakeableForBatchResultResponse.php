<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Batches;

use Anthropic\Responses\Batches\BatchResultResponse;
use Http\Discovery\Psr17FactoryDiscovery;

trait FakeableForBatchResultResponse
{
    /**
     * @param  resource  $resource
     */
    public static function fake($resource = null): BatchResultResponse
    {
        if ($resource === null) {
            $filename = str_replace(['Anthropic\Responses', '\\'], [__DIR__.'/../../Fixtures/', '/'], static::class).'Fixture.jsonl';
            $resource = fopen($filename, 'r');
        }

        $stream = Psr17FactoryDiscovery::findStreamFactory()
            ->createStreamFromResource($resource);

        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse()
            ->withBody($stream);

        return new BatchResultResponse($response);
    }
}
