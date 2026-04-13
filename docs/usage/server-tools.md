---
title: Server Tools
weight: 5
---

Server tools are built-in tools that Anthropic runs on their infrastructure. Unlike [custom tool use](./tool-use.md) where your code executes the tool, server tools are executed by Claude automatically. You just include them in the `tools` array and the results come back in the response.

The primary server tools are **web search** and **code execution**.

## Web search

Web search lets Claude search the internet and cite sources in its response. Add it to the `tools` array:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'When was Claude Shannon born?'],
    ],
]);
```

A newer version `web_search_20260209` adds dynamic filtering, where Claude writes code to filter search results before they reach the context window. This requires the [code execution](#code-execution) tool to be enabled alongside it.

### Web search options

The web search tool accepts several optional configuration fields:

```php
'tools' => [
    [
        'type' => 'web_search_20250305',
        'name' => 'web_search',
        'max_uses' => 5,
        'allowed_domains' => ['example.com', 'docs.php.net'],
        'blocked_domains' => ['untrusted.com'],
        'user_location' => [
            'type' => 'approximate',
            'city' => 'San Francisco',
            'region' => 'California',
            'country' => 'US',
            'timezone' => 'America/Los_Angeles',
        ],
    ],
]
```

| Option | Purpose |
|--------|---------|
| `max_uses` | Limit the number of searches per request |
| `allowed_domains` | Only include results from these domains |
| `blocked_domains` | Never include results from these domains |
| `user_location` | Localize search results to a geographic area |

The response contains three types of content blocks:

**1. The search query Claude chose:**

```php
$response->content[0]->type;  // 'server_tool_use'
$response->content[0]->id;    // 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE'
$response->content[0]->name;  // 'web_search'
$response->content[0]->input; // ['query' => 'claude shannon birth date']
```

**2. Search results (linked to the tool call by `tool_use_id`):**

```php
$response->content[1]->type;              // 'web_search_tool_result'
$response->content[1]->tool_use_id;       // 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE'
$response->content[1]->content[0]['title']; // 'Claude Shannon - Wikipedia'
$response->content[1]->content[0]['url'];   // 'https://en.wikipedia.org/wiki/Claude_Shannon'
```

**3. Claude's answer with citations:**

```php
$response->content[2]->type; // 'text'
$response->content[2]->text; // 'Claude Shannon was born on April 30, 1916...'

$response->content[2]->citations[0]['type'];      // 'web_search_result_location'
$response->content[2]->citations[0]['title'];      // 'Claude Shannon - Wikipedia'
$response->content[2]->citations[0]['url'];        // 'https://en.wikipedia.org/wiki/Claude_Shannon'
$response->content[2]->citations[0]['cited_text']; // 'Claude Elwood Shannon (April 30, 1916 – ...'
```

Claude may perform multiple searches in a single response if the question requires it. Check the usage tracking to see how many searches were made:

```php
$response->usage->serverToolUse?->webSearchRequests; // 1
```

## Code execution

Code execution gives Claude a sandboxed environment where it can write and run code. This is useful for data analysis, calculations, and generating outputs like charts or files.

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 4096,
    'tools' => [
        ['type' => 'code_execution_20250825', 'name' => 'code_execution'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Run this Python code: print(sum(range(1, 101)))'],
    ],
]);
```

A newer version `code_execution_20260120` adds REPL state persistence and programmatic tool calling from within the sandbox. It's available on Claude Opus 4.5+ and Sonnet 4.5+.

The response includes the tool call and its result:

```php
$response->content[0]->type; // 'server_tool_use'
$response->content[0]->name; // 'bash_code_execution'

$response->content[1]->type;                  // 'bash_code_execution_tool_result'
$response->content[1]->tool_use_id;           // 'srvtoolu_01EWAZ5utP321iRHFdsvbWEV'
$response->content[1]->content['type'];       // 'bash_code_execution_result'
$response->content[1]->content['stdout'];     // '5050'
$response->content[1]->content['return_code']; // 0
```

A `return_code` of `0` means the code ran successfully. Non-zero return codes indicate errors, and `stderr` will contain the error output.

### Container persistence

Code execution runs inside a container that persists for 30 days. This means Claude can build on previous code execution results across multiple requests:

```php
$response->container['id'];         // 'container_011CZcynv5pD9zSXC9hAyeS2'
$response->container['expires_at']; // '2026-04-01T12:28:18.898511Z'
```

To reuse the same container in a follow-up request, pass the container ID as a top-level parameter:

```php
$followUp = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 4096,
    'container' => $response->container['id'],
    'tools' => [
        ['type' => 'code_execution_20250825', 'name' => 'code_execution'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Read the file you created and summarize it.'],
    ],
]);
```

## Result block types

Server tools can produce several result block types depending on what operations Claude performs:

| Block type | Tool | Description |
|-----------|------|-------------|
| `web_search_tool_result` | Web search | Search results with titles and URLs |
| `web_fetch_tool_result` | Web search | Content fetched from a specific URL |
| `code_execution_tool_result` | Code execution | General code execution output |
| `bash_code_execution_tool_result` | Code execution | Bash command output with stdout and return code |
| `text_editor_code_execution_tool_result` | Code execution | File creation or editing results |
| `tool_search_tool_result` | Code execution | Results from searching available tools |

All result blocks follow the same pattern: a `type` field identifying the block, a `tool_use_id` linking it to the originating `server_tool_use` block, and a `content` field with the result data.

When a server tool encounters an error (rate limits, invalid input, etc.), the API still returns a 200 response. The error appears inside the result block's `content` field:

```php
$response->content[1]->type;                     // 'web_search_tool_result'
$response->content[1]->content['type'];          // 'web_search_tool_result_error'
$response->content[1]->content['error_code'];    // 'max_uses_exceeded'
```

## Combining server tools with custom tools

You can mix server tools and your own custom tools in the same request:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 4096,
    'tools' => [
        // Server tool
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
        // Your custom tool
        [
            'name' => 'save_to_database',
            'description' => 'Save research findings to the database',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ],
                'required' => ['title', 'content'],
            ],
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Research the latest PHP release and save your findings.'],
    ],
]);
```

Claude will call the server tools automatically and return [custom tool calls](./tool-use.md) for you to execute.

## Usage tracking

Server tool usage is tracked separately from token usage:

```php
$response->usage->serverToolUse?->webSearchRequests;    // number of web searches
$response->usage->serverToolUse?->webFetchRequests;     // number of URL fetches
$response->usage->serverToolUse?->codeExecutionRequests; // number of code executions
$response->usage->serverToolUse?->toolSearchRequests;    // number of tool searches
```

These counts are `null` when no server tools were used.
