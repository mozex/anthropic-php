<?php

use Anthropic\Resources\Files;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Testing\ClientFake;

it('records a files upload request', function () {
    $fake = new ClientFake([
        FileResponse::fake(),
    ]);

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, 'PDF-CONTENTS');
    rewind($handle);

    $fake->files()->upload(['file' => $handle]);

    $fake->assertSent(Files::class, function ($method, $parameters) {
        return $method === 'upload' &&
            is_array($parameters) &&
            array_key_exists('file', $parameters);
    });
});

it('records a files list request', function () {
    $fake = new ClientFake([
        FileListResponse::fake(),
    ]);

    $fake->files()->list(['limit' => 10]);

    $fake->assertSent(Files::class, function ($method, $parameters) {
        return $method === 'list' &&
            $parameters === ['limit' => 10];
    });
});

it('records a files retrieve metadata request', function () {
    $fake = new ClientFake([
        FileResponse::fake(),
    ]);

    $fake->files()->retrieveMetadata('file_011CNha8iCJcU1wXNR6q4V8w');

    $fake->assertSent(Files::class, function ($method, $id) {
        return $method === 'retrieveMetadata' &&
            $id === 'file_011CNha8iCJcU1wXNR6q4V8w';
    });
});

it('records a files download request', function () {
    $fake = new ClientFake([
        'raw-file-bytes',
    ]);

    $result = $fake->files()->download('file_011CPMxVD3fHLUhvTqtsQA5w');

    expect($result)->toBe('raw-file-bytes');

    $fake->assertSent(Files::class, function ($method, $id) {
        return $method === 'download' &&
            $id === 'file_011CPMxVD3fHLUhvTqtsQA5w';
    });
});

it('records a files delete request', function () {
    $fake = new ClientFake([
        DeletedFileResponse::fake(),
    ]);

    $fake->files()->delete('file_011CNha8iCJcU1wXNR6q4V8w');

    $fake->assertSent(Files::class, function ($method, $id) {
        return $method === 'delete' &&
            $id === 'file_011CNha8iCJcU1wXNR6q4V8w';
    });
});
