<?php

/**
 * @return array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable: bool}
 */
function fileResponse(): array
{
    return [
        'id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'type' => 'file',
        'filename' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024000,
        'created_at' => '2025-01-01T00:00:00Z',
        'downloadable' => false,
    ];
}

/**
 * Composed from `fileResponse()` with a `scope` field added. The Anthropic docs define
 * `BetaFileScope` as `{id: string, type: "session"}` but don't provide a concrete session
 * ID example, so the `scope.id` value is illustrative.
 *
 * @return array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable: bool, scope: array{id: string, type: string}}
 */
function fileScopedResponse(): array
{
    return [
        ...fileResponse(),
        'scope' => [
            'id' => 'session_01AbCdEfGhIjKlMnOpQrStUv',
            'type' => 'session',
        ],
    ];
}

/**
 * @return array{data: array<int, array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable: bool}>, first_id: string, last_id: string, has_more: bool}
 */
function fileListResponse(): array
{
    return [
        'data' => [
            fileResponse(),
        ],
        'first_id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'last_id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'has_more' => false,
    ];
}

/**
 * @return array{id: string, type: string}
 */
function deletedFileResponse(): array
{
    return [
        'id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'type' => 'file_deleted',
    ];
}
