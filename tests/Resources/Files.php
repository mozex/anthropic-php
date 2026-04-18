<?php

use Anthropic\Client;
use Anthropic\Contracts\TransporterContract;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Anthropic\ValueObjects\Transporter\Response;

test('upload', function () {
    $client = mockClient(
        'POST',
        'files',
        [],
        Response::from(fileResponse(), metaHeaders()),
        validateParams: false,
    );

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, 'PDF-CONTENTS');
    rewind($handle);

    $result = $client->files()->upload([
        'file' => $handle,
    ]);

    expect($result)
        ->toBeInstanceOf(FileResponse::class)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file')
        ->filename->toBe('document.pdf')
        ->mimeType->toBe('application/pdf')
        ->sizeBytes->toBe(1024000)
        ->createdAt->toBe('2025-01-01T00:00:00Z')
        ->downloadable->toBeFalse()
        ->scope->toBeNull();

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('list', function () {
    $client = mockClient(
        'GET',
        'files',
        [],
        Response::from(fileListResponse(), metaHeaders()),
        validateParams: false,
    );

    $result = $client->files()->list();

    expect($result)
        ->toBeInstanceOf(FileListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(FileResponse::class)
        ->firstId->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->lastId->toBe('file_011CPMxVD3fHLUhvTqtsQA5w')
        ->hasMore->toBeFalse();

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('list with pagination parameters', function () {
    $client = mockClient(
        'GET',
        'files',
        ['limit' => 5, 'after_id' => 'file_011CNha8iCJcU1wXNR6q4V8w'],
        Response::from(fileListResponse(), metaHeaders()),
    );

    $result = $client->files()->list([
        'limit' => 5,
        'after_id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
    ]);

    expect($result)->toBeInstanceOf(FileListResponse::class);
});

test('retrieve metadata', function () {
    $client = mockClient(
        'GET',
        'files/file_011CNha8iCJcU1wXNR6q4V8w',
        [],
        Response::from(fileResponse(), metaHeaders()),
    );

    $result = $client->files()->retrieveMetadata('file_011CNha8iCJcU1wXNR6q4V8w');

    expect($result)
        ->toBeInstanceOf(FileResponse::class)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->filename->toBe('document.pdf')
        ->mimeType->toBe('application/pdf');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('retrieve metadata with scope', function () {
    $client = mockClient(
        'GET',
        'files/file_011CNha8iCJcU1wXNR6q4V8w',
        [],
        Response::from(fileScopedResponse(), metaHeaders()),
    );

    $result = $client->files()->retrieveMetadata('file_011CNha8iCJcU1wXNR6q4V8w');

    expect($result->scope)
        ->not->toBeNull()
        ->id->toBe('session_01AbCdEfGhIjKlMnOpQrStUv')
        ->type->toBe('session');
});

test('download', function () {
    $client = mockContentClient(
        'GET',
        'files/file_011CPMxVD3fHLUhvTqtsQA5w/content',
        [],
        'raw-file-bytes',
    );

    $result = $client->files()->download('file_011CPMxVD3fHLUhvTqtsQA5w');

    expect($result)->toBe('raw-file-bytes');
});

test('delete', function () {
    $client = mockClient(
        'DELETE',
        'files/file_011CNha8iCJcU1wXNR6q4V8w',
        [],
        Response::from(deletedFileResponse(), metaHeaders()),
    );

    $result = $client->files()->delete('file_011CNha8iCJcU1wXNR6q4V8w');

    expect($result)
        ->toBeInstanceOf(DeletedFileResponse::class)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file_deleted');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('every files method auto-injects the files-api-2025-04-14 beta header', function (
    string $transporterMethod,
    Closure $invoke,
    Closure $responseFactory,
) {
    $transporter = Mockery::mock(TransporterContract::class);

    $response = $responseFactory();

    $capturedPayload = null;
    $transporter->shouldReceive($transporterMethod)
        ->once()
        ->andReturnUsing(function (Payload $payload) use (&$capturedPayload, $response) {
            $capturedPayload = $payload;

            return $response;
        });

    $client = new Client($transporter);

    $invoke($client);

    $request = $capturedPayload->toRequest(
        BaseUri::from('api.anthropic.com/v1'),
        Headers::withAuthorization(ApiKey::from('foo')),
        QueryParams::create(),
    );

    expect($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
})->with([
    'upload' => [
        'requestObject',
        fn (Client $client) => $client->files()->upload(['file' => 'bytes']),
        fn () => Response::from(fileResponse(), metaHeaders()),
    ],
    'list' => [
        'requestObject',
        fn (Client $client) => $client->files()->list(),
        fn () => Response::from(fileListResponse(), metaHeaders()),
    ],
    'retrieveMetadata' => [
        'requestObject',
        fn (Client $client) => $client->files()->retrieveMetadata('file_011CNha8iCJcU1wXNR6q4V8w'),
        fn () => Response::from(fileResponse(), metaHeaders()),
    ],
    'download' => [
        'requestContent',
        fn (Client $client) => $client->files()->download('file_011CPMxVD3fHLUhvTqtsQA5w'),
        fn () => 'raw-file-bytes',
    ],
    'delete' => [
        'requestObject',
        fn (Client $client) => $client->files()->delete('file_011CNha8iCJcU1wXNR6q4V8w'),
        fn () => Response::from(deletedFileResponse(), metaHeaders()),
    ],
]);

test('user-provided betas merge with the auto-injected files-api beta', function () {
    $transporter = Mockery::mock(TransporterContract::class);

    $capturedPayload = null;
    $transporter->shouldReceive('requestObject')
        ->once()
        ->andReturnUsing(function (Payload $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return Response::from(fileListResponse(), metaHeaders());
        });

    $client = new Client($transporter);

    $client->files()->list([
        'limit' => 10,
        'betas' => ['extended-cache-ttl-2025-04-11'],
    ]);

    $request = $capturedPayload->toRequest(
        BaseUri::from('api.anthropic.com/v1'),
        Headers::withAuthorization(ApiKey::from('foo')),
        QueryParams::create(),
    );

    expect($request->getHeaderLine('anthropic-beta'))
        ->toBe('extended-cache-ttl-2025-04-11,files-api-2025-04-14');
});
