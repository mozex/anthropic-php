<?php

declare(strict_types=1);

/**
 * Messages streams from the docs, as server-sent events. The docs show raw SSE only for plain
 * streams; the others are composed from the documented event payloads and blocks, and marked.
 * Values the docs cut with "..." lose the dots, and elided events are left out.
 *
 * @return array<string, string>
 */
function messagesStreamDocsExamples(): array
{
    return [
        // https://platform.claude.com/docs/en/build-with-claude/streaming
        'tool use' => <<<'SSE'
            event: message_start
            data: {"type":"message_start","message":{"id":"msg_014p7gG3wDgGV9EUtLvnow3U","type":"message","role":"assistant","model":"claude-opus-5","stop_sequence":null,"usage":{"input_tokens":472,"output_tokens":2},"content":[],"stop_reason":null}}

            event: content_block_start
            data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}

            event: ping
            data: {"type": "ping"}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Okay"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":","}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" let"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"'s"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" check"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" the"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" weather"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" for"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" San"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" Francisco"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":","}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" CA"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":":"}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":0}

            event: content_block_start
            data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"toolu_01T1x1fJ34qAmk2tNTrN7Up6","name":"get_weather","input":{}}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":""}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"location\":"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":" \"San"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":" Francisc"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"o,"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":" CA\"}"}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":1}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"tool_use","stop_sequence":null},"usage":{"output_tokens":89}}

            event: message_stop
            data: {"type":"message_stop"}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/streaming
        'thinking' => <<<'SSE'
            event: message_start
            data: {"type": "message_start", "message": {"id": "msg_01", "type": "message", "role": "assistant", "content": [], "model": "claude-opus-5-5", "stop_reason": null, "stop_sequence": null}}

            event: content_block_start
            data: {"type": "content_block_start", "index": 0, "content_block": {"type": "thinking", "thinking": "", "signature": ""}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "thinking_delta", "thinking": "I need to find the GCD of 1071 and 462 using the Euclidean algorithm.\n\n1071 = 2 × 462 + 147"}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "thinking_delta", "thinking": "\n462 = 3 × 147 + 21"}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "thinking_delta", "thinking": "\n147 = 7 × 21 + 0"}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "thinking_delta", "thinking": "\nThe remainder is 0, so GCD(1071, 462) = 21."}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "signature_delta", "signature": "EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl1pkiMOYds"}}

            event: content_block_stop
            data: {"type": "content_block_stop", "index": 0}

            event: content_block_start
            data: {"type": "content_block_start", "index": 1, "content_block": {"type": "text", "text": ""}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 1, "delta": {"type": "text_delta", "text": "The greatest common divisor of 1071 and 462 is **21**."}}

            event: content_block_stop
            data: {"type": "content_block_stop", "index": 1}

            event: message_delta
            data: {"type": "message_delta", "delta": {"stop_reason": "end_turn", "stop_sequence": null}}

            event: message_stop
            data: {"type": "message_stop"}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/streaming
        'web search' => <<<'SSE'
            event: message_start
            data: {"type":"message_start","message":{"id":"msg_01G","type":"message","role":"assistant","model":"claude-opus-5-5","content":[],"stop_reason":null,"stop_sequence":null,"usage":{"input_tokens":2679,"cache_creation_input_tokens":0,"cache_read_input_tokens":0,"output_tokens":3}}}

            event: content_block_start
            data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"I'll check"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" the current weather in New York City for you"}}

            event: ping
            data: {"type": "ping"}

            event: content_block_delta
            data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"."}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":0}

            event: content_block_start
            data: {"type":"content_block_start","index":1,"content_block":{"type":"server_tool_use","id":"srvtoolu_014hJH82Qum7Td6UV8gDXThB","name":"web_search","input":{}}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":""}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"query"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"\":"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":" \"weather"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":" NY"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"C to"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"day\"}"}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":1 }

            event: content_block_start
            data: {"type":"content_block_start","index":2,"content_block":{"type":"web_search_tool_result","tool_use_id":"srvtoolu_014hJH82Qum7Td6UV8gDXThB","content":[{"type":"web_search_result","title":"Weather in New York City in May 2025 (New York) - detailed Weather Forecast for a month","url":"https://world-weather.info/forecast/usa/new_york/may-2025/","encrypted_content":"Ev0DCioIAxgCIiQ3NmU4ZmI4OC1k","page_age":null}]}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":2}

            event: content_block_start
            data: {"type":"content_block_start","index":3,"content_block":{"type":"text","text":""}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":3,"delta":{"type":"text_delta","text":"Here's the current weather information for New York"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":3,"delta":{"type":"text_delta","text":" City:\n\n# Weather"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":3,"delta":{"type":"text_delta","text":" in New York City"}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":3,"delta":{"type":"text_delta","text":"\n\n"}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":17}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"end_turn","stop_sequence":null},"usage":{"input_tokens":10682,"cache_creation_input_tokens":0,"cache_read_input_tokens":0,"output_tokens":510,"server_tool_use":{"web_search_requests":1}}}

            event: message_stop
            data: {"type":"message_stop"}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/compaction-on-demand: "one
        // content_block_start event carrying the complete block, then content_block_stop"
        // composed: message_start and message_delta, from the page's response; no SSE example
        'on-demand compaction' => <<<'SSE'
            event: message_start
            data: {"type":"message_start","message":{"id":"msg_013Zva2CMHLNnXjNJJKqJ2EF","type":"message","role":"assistant","model":"claude-opus-5-5","content":[],"stop_reason":null,"stop_sequence":null,"usage":{"input_tokens":0,"output_tokens":0}}}

            event: ping
            data: {"type": "ping"}

            event: content_block_start
            data: {"type":"content_block_start","index":0,"content_block":{"type":"compaction","content":"Summary of the conversation: the user is designing the data model for a recipe app. The entities agreed so far are Recipe, Ingredient, Step, and RecipeIngredient, which holds the quantity and unit. The user then asked for field names for Recipe.","signature":"EuYBCkQY"}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":0}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"compaction","stop_sequence":null},"usage":{"output_tokens":0,"iterations":[{"type":"compaction","input_tokens":144,"output_tokens":276}]}}

            event: message_stop
            data: {"type":"message_stop"}
            SSE,
        // https://platform.claude.com/docs/en/test-and-evaluate/strengthen-guardrails/handle-streaming-refusals:
        // "stop_details arrives on the message_delta event alongside stop_reason"
        // composed: the events around the page's assembled message; message_start and usage from the streaming page
        'refusal' => <<<'SSE'
            event: message_start
            data: {"type": "message_start", "message": {"id": "msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY", "type": "message", "role": "assistant", "content": [], "model": "claude-opus-5-5", "stop_reason": null, "stop_sequence": null, "usage": {"input_tokens": 25, "output_tokens": 1}}}

            event: content_block_start
            data: {"type": "content_block_start", "index": 0, "content_block": {"type": "text", "text": ""}}

            event: content_block_delta
            data: {"type": "content_block_delta", "index": 0, "delta": {"type": "text_delta", "text": "Hello.."}}

            event: content_block_stop
            data: {"type": "content_block_stop", "index": 0}

            event: message_delta
            data: {"type": "message_delta", "delta": {"stop_reason": "refusal", "stop_sequence": null, "stop_details": {"type": "refusal", "category": "cyber", "explanation": "This request was declined because it could enable cyber harm."}}, "usage": {"output_tokens": 15}}

            event: message_stop
            data: {"type": "message_stop"}
            SSE,
        // https://platform.claude.com/docs/en/agents-and-tools/mcp-connector
        // composed: the blocks as content_block_start events; no SSE example. The last result carries its
        // content as a string, which the beta reference allows (string or text blocks) and the page doesn't show
        'mcp connector' => <<<'SSE'
            event: content_block_start
            data: {"type":"content_block_start","index":0,"content_block":{"type":"mcp_tool_listing","mcp_server_name":"example-mcp","tools":[{"name":"echo","description":"Returns the text it receives.","input_schema":{"type":"object","properties":{"text":{"type":"string"}},"required":["text"]}}]}}

            event: content_block_start
            data: {"type":"content_block_start","index":1,"content_block":{"type":"mcp_tool_use","id":"mcptoolu_014Q35RayjACSWkSj4X2yov1","name":"echo","server_name":"example-mcp","input":{}}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"param1\": \"value1\", \"param2\": \"value2\"}"}}

            event: content_block_start
            data: {"type":"content_block_start","index":2,"content_block":{"type":"mcp_tool_result","tool_use_id":"mcptoolu_014Q35RayjACSWkSj4X2yov1","is_error":false,"content":[{"type":"text","text":"Hello"}]}}

            event: content_block_start
            data: {"type":"content_block_start","index":3,"content_block":{"type":"mcp_tool_result","tool_use_id":"mcptoolu_014Q35RayjACSWkSj4X2yov1","is_error":false,"content":"Hello"}}
            SSE,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/advisor-tool: "advisor_tool_result
        // arrives fully formed in a single content_block_start event (no deltas)"
        // composed: the blocks as content_block_start events; no SSE example
        'advisor' => <<<'SSE'
            event: content_block_start
            data: {"type":"content_block_start","index":1,"content_block":{"type":"server_tool_use","id":"srvtoolu_abc123","name":"advisor","input":{}}}

            event: content_block_start
            data: {"type":"content_block_start","index":2,"content_block":{"type":"advisor_tool_result","tool_use_id":"srvtoolu_abc123","content":{"type":"advisor_redacted_result","encrypted_content":"EqQBCkYIBRgCIiQ5ZjE0N2M2OC0yYWIxLTRkZTktYjA3ZC1hZTUyMzkxYjhkMmU"}}}
            SSE,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/computer-use-tool
        // composed: the block as a content_block_start event with its input streamed; no SSE example
        'toolset' => <<<'SSE'
            event: content_block_start
            data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"toolu_01WkoTUvSHDzTBu2xnGk8Ep8","name":"left_click","toolset_name":"computer","input":{}}}

            event: content_block_delta
            data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"coordinate\": [512, 742]}"}}
            SSE,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/programmatic-tool-calling
        // composed: the block as a content_block_start event, and its container on message_delta (the
        // page's non-streaming container); no SSE example
        'programmatic tool calling' => <<<'SSE'
            event: content_block_start
            data: {"type":"content_block_start","index":2,"content_block":{"type":"tool_use","id":"toolu_xyz789","name":"query_database","input":{},"caller":{"type":"code_execution_20260120","tool_id":"srvtoolu_abc123"}}}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"tool_use","stop_sequence":null,"container":{"id":"container_xyz789","expires_at":"2026-01-20T14:30:00Z"}},"usage":{"output_tokens":89}}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/refusals-and-fallback: "a fallback content
        // block arrives at each model boundary as a content_block_start and content_block_stop pair"
        // composed: the page's block and usage as events; no SSE example
        'server-side fallback' => <<<'SSE'
            event: content_block_start
            data: {"type":"content_block_start","index":0,"content_block":{"type":"fallback","from":{"model":"claude-fable-5"},"to":{"model":"claude-opus-4-8"},"trigger":{"type":"refusal","category":"cyber"}}}

            event: content_block_stop
            data: {"type":"content_block_stop","index":0}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"end_turn","stop_sequence":null,"stop_details":null},"usage":{"input_tokens":412,"output_tokens":264,"cache_read_input_tokens":0,"cache_creation_input_tokens":0,"iterations":[{"type":"message","model":"claude-fable-5","input_tokens":535,"output_tokens":0,"cache_read_input_tokens":0,"cache_creation_input_tokens":0},{"type":"fallback_message","model":"claude-opus-4-8","input_tokens":412,"output_tokens":264,"cache_read_input_tokens":0,"cache_creation_input_tokens":0}]}}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/context-editing: "for streaming responses,
        // the context edits are included in the final message_delta event"
        // composed: the page elides applied_edits on the streamed event; these are its non-streaming values
        'context editing' => <<<'SSE'
            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"end_turn","stop_sequence":null},"usage":{"output_tokens":1024},"context_management":{"applied_edits":[{"type":"clear_thinking_20251015","cleared_thinking_turns":3,"cleared_input_tokens":15000},{"type":"clear_tool_uses_20250919","cleared_tool_uses":8,"cleared_input_tokens":50000}]}}
            SSE,
        // https://platform.claude.com/docs/en/build-with-claude/preserved-thinking: on message_start when
        // streaming, and again on the final message_delta after a mid-stream fallback (streaming page)
        // composed: the page's arrays on those events; message_start from the streaming page
        'input transformations' => <<<'SSE'
            event: message_start
            data: {"type":"message_start","message":{"id":"msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY","type":"message","role":"assistant","content":[],"model":"claude-opus-5-5","stop_reason":null,"stop_sequence":null,"usage":{"input_tokens":25,"output_tokens":1},"input_transformations":[{"type":"thinking_dropped","path":"messages.3.content.0","reason":"model_binding_mismatch"}]}}

            event: message_delta
            data: {"type":"message_delta","delta":{"stop_reason":"end_turn","stop_sequence":null},"usage":{"output_tokens":15},"input_transformations":[{"type":"thinking_mismatch_allowed","path":"messages.1.content.0","reason":"prefix_binding_mismatch"}]}
            SSE,
    ];
}
