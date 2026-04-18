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
 * @return array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable: bool}
 */
function fileDownloadableResponse(): array
{
    return [
        'id' => 'file_011CPMxVD3fHLUhvTqtsQA5w',
        'type' => 'file',
        'filename' => 'output.png',
        'mime_type' => 'image/png',
        'size_bytes' => 54321,
        'created_at' => '2025-01-02T12:34:56Z',
        'downloadable' => true,
    ];
}

/**
 * @return array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable: bool, scope: array{id: string, type: string}}
 */
function fileScopedResponse(): array
{
    return [
        'id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'type' => 'file',
        'filename' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024000,
        'created_at' => '2025-01-01T00:00:00Z',
        'downloadable' => false,
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
            fileDownloadableResponse(),
        ],
        'first_id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
        'last_id' => 'file_011CPMxVD3fHLUhvTqtsQA5w',
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
