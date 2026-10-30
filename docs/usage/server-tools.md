---
title: Server Tools
weight: 5
---

Server tools are built-in tools that Anthropic runs on their infrastructure. Unlike [custom tool use](./tool-use.md) where your code executes the tool, server tools are executed by Claude automatically. You just include them in the `tools` array and the results come back in the response.

The primary server tools are **web search** and **code execution**. This page also covers the **MCP connector** and the **advisor tool**, which run on Anthropic's side the same way.

## Web search

Web search lets Claude search the internet and cite sources in its response. Add it to the `tools` array:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'When was Claude Shannon born?'],
    ],
]);
```

A newer version `web_search_20260209` adds dynamic filtering, where Claude writes code to filter search results before they reach the context window. This requires the [code execution](#code-execution) tool to be enabled alongside it. The latest versions, `web_search_20260318` and `web_fetch_20260318`, add a `response_inclusion` parameter that drops already-consumed result blocks from the response, which keeps agentic conversations smaller.

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
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 4096,
    'tools' => [
        ['type' => 'code_execution_20250825', 'name' => 'code_execution'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Run this Python code: print(sum(range(1, 101)))'],
    ],
]);
```

A newer version `code_execution_20260120` adds REPL state persistence and programmatic tool calling from within the sandbox. It's available on Claude Opus 4.5+ and Sonnet 4.5+. The latest version, `code_execution_20260521`, also discloses the 90-second per-cell execution limit in the tool description so Claude can budget long-running cells.

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
    'model' => 'claude-sonnet-5-5',
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

### File uploads

When Claude writes a file inside the container, a `container_upload` block shows up in the response pointing at the stored file:

```php
foreach ($response->content as $block) {
    if ($block->type === 'container_upload') {
        $block->file_id; // 'file_01ABCDefGhIjKlMnOpQrStUv'
    }
}
```

Use the `file_id` with the [Files API](https://platform.claude.com/docs/en/api/files) to download the file, forward it to a user, or reference it in a later request.

### Calling your tools from code execution

With programmatic tool calling, the code Claude writes in the sandbox can call your own tools, which cuts model round trips in multi-tool work. Mark the tools it may call with `allowed_callers`, alongside `code_execution_20260120` or later:

```php
$tools = [
    ['type' => 'code_execution_20260120', 'name' => 'code_execution'],
    [
        'name' => 'query_database',
        'description' => 'Run a read-only SQL query against the sales database.',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['sql' => ['type' => 'string']],
            'required' => ['sql'],
        ],
        'allowed_callers' => ['code_execution_20260120'],
    ],
];
```

When that code calls your tool, the sandbox pauses and the response stops with `stop_reason: 'tool_use'`, a `container`, and a `tool_use` block whose `caller` names the code execution run:

```php
$block->type;            // 'tool_use'
$block->name;            // 'query_database'
$block->caller->type;    // 'code_execution_20260120'
$block->caller->tool_id; // 'srvtoolu_abc123', the code execution block that called it
```

Run the tool as you would a direct call, and answer before the pending call times out (about four minutes). The reply has stricter rules than a normal tool result:

- The `user` message holds only `tool_result` blocks, one for every pending call, with no text after them.
- Each result's `content` is a string or `text` blocks.
- The request passes the paused response's `container` ID (the API rejects it without one) and the same `tools`.

```php
$followUp = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 4096,
    'container' => $response->container['id'],
    'tools' => $tools,
    'messages' => [
        ...$messages,
        ['role' => 'assistant', 'content' => $response->toArray()['content']],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode($rows),
                ],
            ],
        ],
    ],
]);
```

`server_tool_use` blocks carry a `caller` too, for example a web search the code made, and so do their result blocks. Those run on Anthropic's side, so there's nothing for you to answer. Response blocks name the caller `code_execution_20260120` whichever code execution version the request used.

## Result block types

Server tools can produce several result block types depending on what operations Claude performs:

| Block type | Tool | Description |
|-----------|------|-------------|
| `web_search_tool_result` | Web search | Search results with titles and URLs |
| `web_fetch_tool_result` | Web search | Content fetched from a specific URL |
| `code_execution_tool_result` | Code execution | General code execution output |
| `bash_code_execution_tool_result` | Code execution | Bash command output with stdout and return code |
| `text_editor_code_execution_tool_result` | Code execution | File creation or editing results |
| `tool_search_tool_result` | Tool search | Results from searching available tools |
| `advisor_tool_result` | Advisor | The advisor model's advice, usually encrypted |
| `mcp_tool_result` | MCP connector | The result of a tool on an MCP server, linked to an `mcp_tool_use` block |

All result blocks follow the same pattern: a `type` field identifying the block, a `tool_use_id` linking it to the originating tool block, and a `content` field with the result data.

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
    'model' => 'claude-sonnet-5-5',
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

## MCP connector

The MCP connector lets the API call tools on a remote MCP server for you, with no MCP client in your code. It's in beta: list the servers in `mcp_servers` and reference each one from an `mcp_toolset` entry in `tools`:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'betas' => ['mcp-client-2025-11-20'],
    'mcp_servers' => [
        [
            'type' => 'url',
            'url' => 'https://example-server.modelcontextprotocol.io/sse',
            'name' => 'example-mcp',
            'authorization_token' => $token,
        ],
    ],
    'tools' => [
        ['type' => 'mcp_toolset', 'mcp_server_name' => 'example-mcp'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What tools do you have available?'],
    ],
]);
```

The server `url` must use `https://`, and every server needs exactly one toolset. `default_config` and `configs` on the toolset allow or block single tools (`'configs' => ['echo' => ['enabled' => false]]`).

A call and its result come back as two blocks:

```php
$response->content[0]->type;        // 'mcp_tool_use'
$response->content[0]->id;          // 'mcptoolu_014Q35RayjACSWkSj4X2yov1'
$response->content[0]->name;        // 'echo'
$response->content[0]->server_name; // 'example-mcp'
$response->content[0]->input;       // ['param1' => 'value1', 'param2' => 'value2']

$response->content[1]->type;        // 'mcp_tool_result'
$response->content[1]->tool_use_id; // 'mcptoolu_014Q35RayjACSWkSj4X2yov1'
$response->content[1]->is_error;    // false
$response->content[1]->content;     // [['type' => 'text', 'text' => 'Hello']]
```

The result's `content` can also be a plain string. Your code never runs these calls; they're a record of what happened on the server.

The newer `mcp-client-2026-09-15` header (Claude API only) includes everything the first one does and adds tool listings. When the API asks a server for its tools while answering, the response starts with an `mcp_tool_listing` block, so code that reads `content[0]` should skip it:

```php
$response->content[0]->type;            // 'mcp_tool_listing'
$response->content[0]->mcp_server_name; // 'example-mcp'
$response->content[0]->tools;           // [['name' => 'echo', 'description' => '...', 'input_schema' => [...]]]
```

Send the assistant message back unchanged, listing blocks included, and keep sending `mcp-client-2026-09-15` on every request that carries one. To pin a server's tools so the API stops asking it, copy a listing's `tools` into that server's toolset as `'tools' => [...]`.

## Advisor tool

The advisor tool lets the model you call consult a stronger model mid-answer. It's in beta behind the `advisor-tool-2026-03-01` header:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 4096,
    'betas' => ['advisor-tool-2026-03-01'],
    'tools' => [
        [
            'type' => 'advisor_20260301',
            'name' => 'advisor',
            'model' => 'claude-opus-5-5',
            'max_uses' => 3,
        ],
    ],
    'messages' => $messages,
]);
```

The advisor must be Claude Sonnet 4.6 or a more capable model, and at least as capable as the model you call; an invalid pair returns a 400. `max_tokens` on the tool (1024 or more) caps the advisor's own output.

Each consultation shows up as a `server_tool_use` block named `advisor` and an `advisor_tool_result` block:

```php
$block->type;                         // 'advisor_tool_result'
$block->tool_use_id;                  // 'srvtoolu_abc123'
$block->content['type'];              // 'advisor_redacted_result'
$block->content['encrypted_content']; // 'EqQBCkYIBRgCIiQ5ZjE0N2M2OC0yYWIx...'
```

Every Claude 5 advisor except Sonnet 5 returns only the encrypted `advisor_redacted_result`, so the advice itself isn't readable; the others return an `advisor_result` with `text`. A failed consultation has `content['type']` set to `advisor_tool_result_error` with an `error_code` such as `overloaded` or `max_uses_exceeded`. The advisor's tokens are billed as an `advisor_message` entry in `usage->iterations`.

Send the assistant content back on later turns with the result blocks unchanged. You can drop the advisor from `tools` on a follow-up turn, but keep the beta header while those blocks are in the history.

## Usage tracking

Server tool usage is tracked separately from token usage:

```php
$response->usage->serverToolUse?->webSearchRequests;    // number of web searches
$response->usage->serverToolUse?->webFetchRequests;     // number of URL fetches
$response->usage->serverToolUse?->codeExecutionRequests; // number of code executions
$response->usage->serverToolUse?->toolSearchRequests;    // number of tool searches
```

These counts are `null` when no server tools were used.

---

For pricing, supported models, and advanced configuration, see the [Web search tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/web-search-tool), [Code execution tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/code-execution-tool), [Programmatic tool calling](https://platform.claude.com/docs/en/agents-and-tools/tool-use/programmatic-tool-calling), [MCP connector](https://platform.claude.com/docs/en/agents-and-tools/mcp-connector) and [Advisor tool](https://platform.claude.com/docs/en/agents-and-tools/tool-use/advisor-tool) guides on the Anthropic docs.
