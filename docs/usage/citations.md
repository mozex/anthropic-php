---
title: Citations
weight: 6
---

Citations let you verify the sources behind Claude's claims. When enabled, Claude breaks its response into multiple text blocks where each cited claim includes a `citations` array pointing to exact locations in your source material.

## Document citations

To enable citations, pass documents as content blocks with `'citations' => ['enabled' => true]`:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 1024,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'document',
                    'source' => [
                        'type' => 'text',
                        'media_type' => 'text/plain',
                        'data' => 'The grass is green. The sky is blue.',
                    ],
                    'title' => 'My Document',
                    'citations' => ['enabled' => true],
                ],
                [
                    'type' => 'text',
                    'text' => 'What color is the grass and sky?',
                ],
            ],
        ],
    ],
]);
```

The response comes back as multiple text blocks. Some have citations, some don't:

```php
// Uncited text (introductory phrase)
$response->content[0]->type;      // 'text'
$response->content[0]->text;      // 'According to the document, '
$response->content[0]->citations; // null

// Cited claim
$response->content[1]->type;      // 'text'
$response->content[1]->text;      // 'the grass is green'
$response->content[1]->citations[0]['type'];           // 'char_location'
$response->content[1]->citations[0]['cited_text'];     // 'The grass is green.'
$response->content[1]->citations[0]['document_index']; // 0
$response->content[1]->citations[0]['document_title']; // 'My Document'
$response->content[1]->citations[0]['start_char_index']; // 0
$response->content[1]->citations[0]['end_char_index'];   // 20
```

Each citation pinpoints exactly where in the source document the claim comes from.

## Citation location types

Five citation types exist, depending on the source:

| Type | Source | Location fields |
|------|--------|-----------------|
| `char_location` | Plain text documents | `document_index`, `document_title`, `start_char_index`, `end_char_index` |
| `page_location` | PDF documents | `document_index`, `document_title`, `start_page_number`, `end_page_number` |
| `content_block_location` | Custom content blocks | `document_index`, `document_title`, `start_block_index`, `end_block_index` |
| `web_search_result_location` | Web search results | `url`, `title`, `encrypted_index` |
| `search_result_location` | Search results | `search_result_index`, `source`, `title`, `start_block_index`, `end_block_index` |

All citation types include `cited_text` with the exact text being cited. The first three types (document-based) also include `document_index` and `document_title` to identify the source document.

## Web search citations

When using [web search](./server-tools.md), citations are included automatically. You don't need to enable them separately:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'When was PHP 8.4 released?'],
    ],
]);

// Text blocks in the response will have web search citations
$response->content[2]->citations[0]['type'];  // 'web_search_result_location'
$response->content[2]->citations[0]['url'];   // 'https://www.php.net/releases/8.4/en.php'
$response->content[2]->citations[0]['title']; // 'PHP: PHP 8.4 Release Announcement'
```

## Streaming citations

When [streaming](./streaming.md), citations arrive as `citations_delta` events on the delta object:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 1024,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'document',
                    'source' => [
                        'type' => 'text',
                        'media_type' => 'text/plain',
                        'data' => 'PHP 8.4 was released on November 21, 2024.',
                    ],
                    'title' => 'Release Notes',
                    'citations' => ['enabled' => true],
                ],
                [
                    'type' => 'text',
                    'text' => 'When was PHP 8.4 released?',
                ],
            ],
        ],
    ],
]);

foreach ($stream as $response) {
    if ($response->delta->type === 'citations_delta') {
        $response->delta->citation['type'];      // 'char_location'
        $response->delta->citation['cited_text']; // 'PHP 8.4 was released on November 21, 2024.'
    }
}
```

## Multiple documents

You can pass multiple documents in a single request. The `document_index` in each citation tells you which document the claim comes from (zero-indexed, matching the order you provided them):

```php
'content' => [
    [
        'type' => 'document',
        'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => '...'],
        'title' => 'Document A',          // document_index: 0
        'citations' => ['enabled' => true],
    ],
    [
        'type' => 'document',
        'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => '...'],
        'title' => 'Document B',          // document_index: 1
        'citations' => ['enabled' => true],
    ],
    [
        'type' => 'text',
        'text' => 'Compare these two documents.',
    ],
]
```

---

For document types, chunking behavior, and token cost details, see the [Citations guide](https://platform.claude.com/docs/en/build-with-claude/citations) on the Anthropic docs.
