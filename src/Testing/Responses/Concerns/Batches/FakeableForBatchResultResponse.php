<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Batches;

use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Testing\Responses\Concerns\FakesStreamBodies;

trait FakeableForBatchResultResponse
{
    use FakesStreamBodies;

    /**
     * The fixture's results, or those of a JSONL resource or a list of result arrays, as the results endpoint sends
     * them: `['custom_id' => 'my-first-request', 'result' => ['type' => 'succeeded', 'message' => [...]]]`. `$headers`
     * go on the response, which `meta()` reads; the other fakes' rate-limit headers and request ID without them.
     *
     * @param  resource|list<array<string, mixed>>|null  $source
     * @param  array<string, string|list<string>>|null  $headers
     */
    public static function fake($source = null, ?array $headers = null): BatchResultResponse
    {
        if (is_array($source)) {
            $source = implode('', array_map(fn (array $result): string => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n", $source));
        }

        $source ??= fopen(str_replace(['Anthropic\Responses', '\\'], [__DIR__.'/../../Fixtures/', '/'], self::class).'Fixture.jsonl', 'r');

        return new BatchResultResponse(self::fakeHttpResponse($source, $headers ?? self::fakeMetaHeaders()));
    }
}
