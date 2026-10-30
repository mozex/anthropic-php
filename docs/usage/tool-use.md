---
title: Tool Use
weight: 3
---

Tool use (sometimes called "function calling") lets you give Claude a set of custom tools it can call during a conversation. You define the tools, Claude decides when to call them and with what arguments, and your code executes them and sends the results back.

This is how you connect Claude to external data sources, APIs, databases, or any functionality your application provides.

## Defining tools

Tools are defined in the `tools` array. Each tool needs a `name`, `description`, and an `input_schema` that describes the expected arguments using [JSON Schema](https://json-schema.org/):

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA',
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'The unit of temperature',
                    ],
                ],
                'required' => ['location'],
            ],
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
    ],
]);
```

Write clear descriptions for both the tool and its parameters. Claude uses these descriptions to decide when and how to call each tool.

## Reading tool calls

When Claude decides to use a tool, the response contains `tool_use` content blocks alongside any text:

```php
// Claude often explains what it's about to do
$response->content[0]->type; // 'text'
$response->content[0]->text; // 'I'll check the weather in San Francisco for you.'

// Then the tool call itself
$response->content[1]->type;              // 'tool_use'
$response->content[1]->id;                // 'toolu_01RnYGkgJusAzXvcySfZ2Dq7'
$response->content[1]->name;              // 'get_weather'
$response->content[1]->input['location']; // 'San Francisco, CA'
$response->content[1]->input['unit'];     // 'fahrenheit'
```

The `stop_reason` will be `'tool_use'` instead of `'end_turn'` when Claude wants to call a tool:

```php
$response->stop_reason; // 'tool_use'
```

### Who invoked the tool

Every `tool_use` block also carries a `caller` object that says whether Claude asked for the tool directly or called it from code it runs in the code execution sandbox ([programmatic tool calling](./server-tools.md#calling-your-tools-from-code-execution)):

```php
$block = $response->content[1];

$block->caller->type;    // 'direct' for normal tool calls
$block->caller->tool_id; // null when type is 'direct'
```

For a call made from code, `caller->type` is `code_execution_20260120` and `caller->tool_id` is the `id` of the code execution block that made it. Run it like any other call: the sandbox is paused until your result arrives, and it gives up after about four minutes. Only the answer differs, as the [server tools page](./server-tools.md#calling-your-tools-from-code-execution) explains.

## Sending tool results back

After executing the tool, you send the result back to Claude in a `tool_result` content block. Include the full conversation history so Claude has context:

```php
// 1. First request: Claude asks to call a tool
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'tools' => $tools, // same tools array as before
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
    ],
]);

// 2. Your code executes the tool
$weatherData = getWeather('San Francisco, CA', 'fahrenheit');

// 3. Second request: send the result back
$followUp = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'tools' => $tools,
    'messages' => [
        // Original user message
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
        // Claude's response (including the tool call)
        ['role' => 'assistant', 'content' => $response->toArray()['content']],
        // Your tool result
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $response->content[1]->id,
                    'content' => json_encode($weatherData),
                ],
            ],
        ],
    ],
]);

// Claude now responds with the weather information in natural language
echo $followUp->content[0]->text;
// "The current weather in San Francisco is 65°F (18°C) with partly cloudy skies."
```

The `tool_use_id` in the result must match the `id` from Claude's tool call. This links the result to the correct tool invocation.

## Multiple tool calls

Claude can call multiple tools in a single response. When it does, the content array contains multiple `tool_use` blocks:

```php
foreach ($response->content as $block) {
    if ($block->type === 'tool_use') {
        // Execute each tool and collect results
        $results[] = [
            'type' => 'tool_result',
            'tool_use_id' => $block->id,
            'content' => executeMyTool($block->name, $block->input),
        ];
    }
}
```

Send all results back in a single message.

## Computer and browser toolsets

Computer use and browser use come as toolsets: one entry in `tools` gives Claude a family of member tools (`screenshot`, `left_click`, `navigate`, `read_page` and so on), which your code carries out. Neither needs a beta header:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 4096,
    'tools' => [
        ['type' => 'computer_toolset_20260801'],
        ['type' => 'browser_toolset_20260801'],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Find a picture of a cat.'],
    ],
]);
```

`configs` turns members on or off (`'configs' => ['zoom' => ['enabled' => false]]`). Claude 5.5 models take computer use only through `computer_toolset_20260801` on the Claude API; older models such as Opus 4.6 and Sonnet 4.6 use `computer_20251124` with the `computer-use-2025-11-24` beta.

A call to a member tool has its toolset's name in `toolset_name`:

```php
$response->content[1]->type;                // 'tool_use'
$response->content[1]->name;                // 'left_click'
$response->content[1]->toolset_name;        // 'computer'
$response->content[1]->input['coordinate']; // [512, 742]
```

Dispatch on the pair of `toolset_name` and `name`, never on `name` alone: a custom tool in the same request can share a member's name, `screenshot` exists in both toolsets, and a later toolset version can add members. A custom tool's call has `toolset_name` set to `null`. Every result for a member tool must echo the same `toolset_name`, and only member results carry it; the API rejects a result that leaves it out or names another toolset:

```php
foreach ($response->content as $block) {
    if ($block->type !== 'tool_use') {
        continue;
    }

    $result = match ($block->toolset_name) {
        'computer' => $computer->run($block->name, $block->input),
        'browser' => $browser->run($block->name, $block->input),
        null => executeMyTool($block->name, $block->input),
    };

    $results[] = array_filter([
        'type' => 'tool_result',
        'tool_use_id' => $block->id,
        'toolset_name' => $block->toolset_name,
        'content' => $result,
    ], fn ($value) => $value !== null);
}
```

A computer member's result can hold only `text` and `image` blocks. A browser member's can also hold a `browser_state` block, and the tab tools answer with exactly one `browser_state` block.

## Error results

If a tool execution fails, you can tell Claude by setting `is_error` to `true`:

```php
[
    'type' => 'tool_result',
    'tool_use_id' => $toolCallId,
    'is_error' => true,
    'content' => 'Location not found. Please check the city name.',
]
```

Claude will typically apologize and either try again with different arguments or ask the user for clarification.

## Strict schema validation

Add `'strict' => true` to a tool definition to guarantee Claude's tool calls always match your schema exactly:

```php
[
    'name' => 'get_weather',
    'description' => 'Get the current weather in a given location',
    'strict' => true,
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string'],
        ],
        'required' => ['location'],
    ],
]
```

Without `strict`, Claude usually follows the schema but might occasionally produce unexpected fields or types. With `strict`, the output is guaranteed to conform.

## Controlling tool use

You can influence when Claude uses tools with the `tool_choice` parameter:

```php
// Force Claude to use a specific tool
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => $tools,
    'tool_choice' => ['type' => 'tool', 'name' => 'get_weather'],
    'messages' => $messages,
]);

// Force Claude to use at least one tool (any tool)
'tool_choice' => ['type' => 'any']

// Let Claude decide (default behavior)
'tool_choice' => ['type' => 'auto']

// Prevent Claude from using any tools
'tool_choice' => ['type' => 'none']
```

Forcing a tool doesn't work everywhere. Claude Opus 5.5, Sonnet 5.5, Fable 5.1 and Mythos 5.1 return a 400 for `any` and `tool`; use `auto` with [strict tool use](#strict-schema-validation) or structured outputs there. Budget-based [thinking](./thinking.md#budget-based-thinking) (`'type' => 'enabled'`) also supports only `auto` and `none`. Where forcing is accepted, the response starts with the `tool_use` block and has no `thinking` block.

## Streaming with tool use

Tool calls also work with streaming. See [Streaming](./streaming.md) for how tool input arrives as `input_json_delta` events.

---

For all tool definition options, tool choice behavior, and advanced patterns, see the [Tool use guide](https://platform.claude.com/docs/en/agents-and-tools/tool-use/overview) on the Anthropic docs.
