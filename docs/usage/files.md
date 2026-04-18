---
title: Files
weight: 10
---

The Files API lets you upload documents once and reference them by `file_id` in any later Messages call. Anthropic currently flags this endpoint as beta on their side; the SDK sends the required header for you, so there's nothing to configure.

## Uploading a file

Pass any readable resource under the `file` key. The client streams it to `/v1/files` as `multipart/form-data` and gives you back the metadata:

```php
$response = $client->files()->upload([
    'file' => fopen('/path/to/document.pdf', 'r'),
]);

$response->id;           // 'file_011CNha8iCJcU1wXNR6q4V8w'
$response->type;         // 'file'
$response->filename;     // 'document.pdf'
$response->mimeType;     // 'application/pdf'
$response->sizeBytes;    // 1024000
$response->createdAt;    // '2025-01-01T00:00:00Z'
$response->downloadable; // false
```

Supported types map to content blocks like this:

| File type | MIME | Use it in |
|-----------|------|-----------|
| PDF | `application/pdf` | `document` block |
| Plain text | `text/plain` | `document` block |
| Images | `image/jpeg`, `image/png`, `image/gif`, `image/webp` | `image` block |
| Code execution inputs | varies (CSV, XLSX, JSON, etc.) | `container_upload` block |

Max file size is 500 MB. The storage ceiling is 500 GB per organization.

Keep the `id` wherever you'd normally keep a file path. You'll hand it back to the Messages API as a `file_id`.

## Referencing a file in a message

Once you have an `id`, drop it into a content block. For a PDF or text file, that's a `document` block with `source.type` set to `file`. Note the `betas` key on the Messages call: the Messages endpoint only accepts `source.type: file` when the `files-api-2025-04-14` beta header is on that specific request. The SDK auto-injects it on `$client->files()` calls but doesn't know whether any given Messages call references a file_id, so you pass it explicitly here:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 1024,
    'betas' => ['files-api-2025-04-14'],
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Summarise this document.'],
                [
                    'type' => 'document',
                    'source' => [
                        'type' => 'file',
                        'file_id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
                    ],
                ],
            ],
        ],
    ],
]);
```

If every Messages call in your app references uploaded files, skip the per-call `betas` and set the header globally on the factory instead:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpHeader('anthropic-beta', 'files-api-2025-04-14')
    ->make();
```

For images, swap `document` for `image`:

```php
[
    'type' => 'image',
    'source' => [
        'type' => 'file',
        'file_id' => 'file_011CPMxVD3fHLUhvTqtsQA5w',
    ],
]
```

Same file can be referenced from any number of requests, which is the point of the whole API.

## Listing files

`list()` returns a cursor-paginated page of files in the workspace tied to your API key:

```php
$response = $client->files()->list(['limit' => 20]);

foreach ($response->data as $file) {
    $file->id;
    $file->filename;
    $file->sizeBytes;
}

$response->firstId; // 'file_011CNha8iCJcU1wXNR6q4V8w'
$response->lastId;  // 'file_011CPMxVD3fHLUhvTqtsQA5w'
$response->hasMore; // false
```

Pagination works like the Models and Batches endpoints. `limit` goes up to 1000 and defaults to 20. Use `after_id` with the previous page's `lastId` to walk forward, or `before_id` with `firstId` to walk backward:

```php
$page1 = $client->files()->list(['limit' => 100]);

while ($page1->hasMore) {
    $page1 = $client->files()->list([
        'limit' => 100,
        'after_id' => $page1->lastId,
    ]);
}
```

There's also an optional `scope_id` parameter to filter by session scope, which matters if you're using files inside a session.

## Retrieving metadata

`retrieveMetadata()` is a GET on a single file. Same response shape as upload:

```php
$file = $client->files()->retrieveMetadata('file_011CNha8iCJcU1wXNR6q4V8w');

$file->filename;     // 'document.pdf'
$file->mimeType;     // 'application/pdf'
$file->sizeBytes;    // 1024000
$file->downloadable; // false
```

For session-scoped files (created by the code execution tool or Skills API), `scope` tells you where it came from:

```php
$file->scope?->type; // 'session'
$file->scope?->id;   // 'session_01AbCdEfGhIjKlMnOpQrStUv'
```

## Downloading a file

Only files created by the [code execution tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/code-execution-tool) or [Skills](https://platform.claude.com/docs/en/build-with-claude/skills-guide) can be downloaded. Files you upload yourself can't be read back. `downloadable` on the metadata tells you which is which.

`download()` returns the raw bytes as a string:

```php
$bytes = $client->files()->download('file_011CPMxVD3fHLUhvTqtsQA5w');

file_put_contents('output.png', $bytes);
```

For a large download, wrap the write in something that writes to disk as it goes rather than holding the whole thing in memory. If you're building on top of Guzzle or Symfony, you can swap the transporter out via the factory and stream directly.

## Deleting a file

```php
$response = $client->files()->delete('file_011CNha8iCJcU1wXNR6q4V8w');

$response->id;   // 'file_011CNha8iCJcU1wXNR6q4V8w'
$response->type; // 'file_deleted'
```

Deletes are permanent. Files still being referenced in an in-flight Messages call may keep working briefly, but new requests using that `file_id` will fail with a 404.

## Errors to expect

The common ones, from the [Anthropic docs](https://platform.claude.com/docs/en/build-with-claude/files):

- `404` if the `file_id` doesn't exist or belongs to another workspace.
- `400` if you use the wrong block type for the file (e.g., image file inside a `document` block).
- `400` if the filename breaks the rules (1 to 255 characters, no `< > : " | ? * \ /` or control characters).
- `413` if the file is over 500 MB.
- `403` if your organization is over the 500 GB total.

All of these surface as `Anthropic\Exceptions\ErrorException` with the usual `message` and `type` on the payload.

## Rate limits

Anthropic caps file-related calls at roughly 100 per minute. Normal messages and token usage rate limits still apply on top.

File operations themselves (upload, list, retrieve, download, delete) are free. Tokens are only charged when the file is actually used inside a Messages call.

---

For the full API reference, see the [Files API guide](https://platform.claude.com/docs/en/build-with-claude/files) and the [Files endpoint reference](https://platform.claude.com/docs/en/api/files-list) on the Anthropic docs.
