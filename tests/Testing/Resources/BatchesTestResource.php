<?php

use Anthropic\Resources\Batches;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Testing\ClientFake;

it('records a batches create request', function () {
    $fake = new ClientFake([
        BatchResponse::fake(),
    ]);

    $fake->batches()->create([
        'requests' => [
            [
                'custom_id' => 'request-1',
                'params' => [
                    'model' => 'claude-sonnet-4-6',
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => 'Hello!']],
                ],
            ],
        ],
    ]);

    $fake->assertSent(Batches::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['requests'][0]['custom_id'] === 'request-1';
    });
});

it('records a batches retrieve request', function () {
    $fake = new ClientFake([
        BatchResponse::fake(),
    ]);

    $fake->batches()->retrieve('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    $fake->assertSent(Batches::class, function ($method, $id) {
        return $method === 'retrieve' &&
            $id === 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x';
    });
});

it('records a batches list request', function () {
    $fake = new ClientFake([
        BatchListResponse::fake(),
    ]);

    $fake->batches()->list(['limit' => 10]);

    $fake->assertSent(Batches::class, function ($method, $parameters) {
        return $method === 'list' &&
            $parameters === ['limit' => 10];
    });
});

it('records a batches cancel request', function () {
    $fake = new ClientFake([
        BatchResponse::fake(),
    ]);

    $fake->batches()->cancel('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    $fake->assertSent(Batches::class, function ($method, $id) {
        return $method === 'cancel' &&
            $id === 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x';
    });
});

it('records a batches delete request', function () {
    $fake = new ClientFake([
        DeletedBatchResponse::fake(),
    ]);

    $fake->batches()->delete('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    $fake->assertSent(Batches::class, function ($method, $id) {
        return $method === 'delete' &&
            $id === 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x';
    });
});

it('records a batches results request', function () {
    $fake = new ClientFake([
        BatchResultResponse::fake(),
    ]);

    $fake->batches()->results('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    $fake->assertSent(Batches::class, function ($method, $id) {
        return $method === 'results' &&
            $id === 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x';
    });
});
