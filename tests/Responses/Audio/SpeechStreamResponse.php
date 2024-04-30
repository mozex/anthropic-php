<?php

use Anthropic\Responses\Audio\SpeechStreamResponse;
use Anthropic\Responses\Meta\MetaInformation;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

test('from response', function () {
    $response = new Response(
        body: new Stream(speechStream()),
        headers: metaHeaders(),
    );

    $speech = new SpeechStreamResponse($response);

    expect($speech)
        ->toBeInstanceOf(SpeechStreamResponse::class)
        ->getIterator()->toBeInstanceOf(Iterator::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('fake', function () {
    $response = SpeechStreamResponse::fake();

    foreach ($response as $chunk) {
        expect($chunk)->toBeString();
    }
});

test('fake with override', function () {
    $response = SpeechStreamResponse::fake('fake audio');

    foreach ($response as $chunk) {
        expect($chunk)->toBe('fake audio');
    }
});
