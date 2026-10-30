<?php

declare(strict_types=1);

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateStreamedResponse;

test('without iterations, the helpers count the top-level usage', function () {
    // composed: a call with cache writes and reads
    $usage = CreateResponseUsage::from(['input_tokens' => 10, 'output_tokens' => 20, 'cache_creation_input_tokens' => 5, 'cache_read_input_tokens' => 100]);

    expect($usage->billedInputTokens())->toBe(115)
        ->and($usage->billedOutputTokens())->toBe(20)
        ->and($usage->billedTokens())->toBe(135)
        // cache reads left out
        ->and($usage->rateLimitTokens())->toBe(35);
});

test('with iterations, the helpers count across them, which the top-level counts leave out', function (string $example, int $input, int $output, int $rate) {
    $usage = CreateResponse::from(json_decode(messagesDocsExamples()[$example], true), meta())->usage;

    expect($usage->billedInputTokens())->toBe($input)
        ->and($usage->billedOutputTokens())->toBe($output)
        ->and($usage->billedTokens())->toBe($input + $output)
        ->and($usage->rateLimitTokens())->toBe($rate);
})->with([
    // the compaction iteration and the answer's: 180,000 + 23,000 in, 3,500 + 1,000 out
    'threshold compaction' => ['threshold compaction', 203000, 4500, 207500],
    // the executor's two turns and the advisor's: 2,583 in and 412 cache reads, 2,143 out
    'the advisor' => ['advisor result', 2995, 2143, 4726],
]);

test('a stream gets the helpers through the DTO of the usage it folded', function () {
    $stream = CreateStreamedResponse::fake();
    iterator_to_array($stream);

    // the fixture's message_start 14 in, and its final message_delta 15 out
    expect(CreateResponseUsage::from($stream->usage())->billedTokens())->toBe(29);
});
