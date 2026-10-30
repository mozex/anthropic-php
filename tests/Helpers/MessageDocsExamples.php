<?php

declare(strict_types=1);

/**
 * Messages API responses from the docs, as JSON text so `{}` and `[]` stay apart. Values the docs
 * cut with "..." lose the dots; envelopes the docs don't show are composed and marked.
 *
 * @return array<string, string>
 */
function messagesDocsExamples(): array
{
    return [
        // https://platform.claude.com/docs/en/build-with-claude/compaction-on-demand
        'on-demand compaction' => <<<'JSON'
            {
              "id": "msg_013Zva2CMHLNnXjNJJKqJ2EF",
              "type": "message",
              "role": "assistant",
              "model": "claude-opus-5-5",
              "content": [
                {
                  "type": "compaction",
                  "content": "Summary of the conversation: the user is designing the data model for a recipe app. The entities agreed so far are Recipe, Ingredient, Step, and RecipeIngredient, which holds the quantity and unit. The user then asked for field names for Recipe.",
                  "signature": "EuYBCkQY"
                }
              ],
              "stop_reason": "compaction",
              "usage": {
                "input_tokens": 0,
                "output_tokens": 0,
                "iterations": [{ "type": "compaction", "input_tokens": 144, "output_tokens": 276 }]
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/compaction-threshold
        // composed: id, type, role, model and stop_reason, no docs example
        'threshold compaction' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "compaction",
                  "content": "Summary of the conversation: The user requested help building a web scraper"
                },
                {
                  "type": "text",
                  "text": "Based on our conversation so far"
                }
              ],
              "stop_reason": "end_turn",
              "usage": {
                "input_tokens": 23000,
                "output_tokens": 1000,
                "iterations": [
                  {
                    "type": "compaction",
                    "input_tokens": 180000,
                    "output_tokens": 3500
                  },
                  {
                    "type": "message",
                    "input_tokens": 23000,
                    "output_tokens": 1000
                  }
                ]
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/mcp-connector
        // composed: the envelope around the three blocks, no docs example
        'mcp connector blocks' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "mcp_tool_listing",
                  "mcp_server_name": "example-mcp",
                  "tools": [
                    {
                      "name": "echo",
                      "description": "Returns the text it receives.",
                      "input_schema": {
                        "type": "object",
                        "properties": { "text": { "type": "string" } },
                        "required": ["text"]
                      }
                    }
                  ]
                },
                {
                  "type": "mcp_tool_use",
                  "id": "mcptoolu_014Q35RayjACSWkSj4X2yov1",
                  "name": "echo",
                  "server_name": "example-mcp",
                  "input": { "param1": "value1", "param2": "value2" }
                },
                {
                  "type": "mcp_tool_result",
                  "tool_use_id": "mcptoolu_014Q35RayjACSWkSj4X2yov1",
                  "is_error": false,
                  "content": [
                    {
                      "type": "text",
                      "text": "Hello"
                    }
                  ]
                }
              ],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/advisor-tool
        // composed: id, type, model and stop_reason, no docs example
        'advisor result' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "text",
                  "text": "Let me consult the advisor on this."
                },
                {
                  "type": "server_tool_use",
                  "id": "srvtoolu_abc123",
                  "name": "advisor",
                  "input": {}
                },
                {
                  "type": "advisor_tool_result",
                  "tool_use_id": "srvtoolu_abc123",
                  "content": {
                    "type": "advisor_result",
                    "text": "Use a channel-based coordination pattern. The tricky part is draining in-flight work during shutdown: close the input channel first, then wait on a WaitGroup"
                  }
                },
                {
                  "type": "text",
                  "text": "Here's the implementation. I'm using a channel-based coordination pattern to avoid writer starvation"
                }
              ],
              "stop_reason": "end_turn",
              "usage": {
                "input_tokens": 1760,
                "cache_read_input_tokens": 412,
                "cache_creation_input_tokens": 0,
                "output_tokens": 531,
                "iterations": [
                  {
                    "type": "message",
                    "input_tokens": 412,
                    "cache_read_input_tokens": 0,
                    "cache_creation_input_tokens": 0,
                    "output_tokens": 89
                  },
                  {
                    "type": "advisor_message",
                    "model": "claude-opus-5-5",
                    "input_tokens": 823,
                    "cache_read_input_tokens": 0,
                    "cache_creation_input_tokens": 0,
                    "output_tokens": 1612
                  },
                  {
                    "type": "message",
                    "input_tokens": 1348,
                    "cache_read_input_tokens": 412,
                    "cache_creation_input_tokens": 0,
                    "output_tokens": 442
                  }
                ]
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/advisor-tool
        // composed: the envelope and the server_tool_use block, no docs example
        'advisor redacted result' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "server_tool_use",
                  "id": "srvtoolu_abc123",
                  "name": "advisor",
                  "input": {}
                },
                {
                  "type": "advisor_tool_result",
                  "tool_use_id": "srvtoolu_abc123",
                  "content": {
                    "type": "advisor_redacted_result",
                    "encrypted_content": "EqQBCkYIBRgCIiQ5ZjE0N2M2OC0yYWIxLTRkZTktYjA3ZC1hZTUyMzkxYjhkMmU"
                  }
                }
              ],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/advisor-tool
        // composed: the envelope and the server_tool_use block, no docs example
        'advisor error' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "server_tool_use",
                  "id": "srvtoolu_abc123",
                  "name": "advisor",
                  "input": {}
                },
                {
                  "type": "advisor_tool_result",
                  "tool_use_id": "srvtoolu_abc123",
                  "content": {
                    "type": "advisor_tool_result_error",
                    "error_code": "overloaded"
                  }
                }
              ],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/computer-use-tool
        // composed: usage, no docs example
        'computer toolset' => <<<'JSON'
            {
              "id": "msg_01UZ3bXcQH8mTqNhVfL9eK2p",
              "type": "message",
              "role": "assistant",
              "model": "claude-opus-5-5",
              "content": [
                {
                  "type": "text",
                  "text": "I'll open the web browser to find a picture of a cat."
                },
                {
                  "type": "tool_use",
                  "id": "toolu_01WkoTUvSHDzTBu2xnGk8Ep8",
                  "name": "left_click",
                  "toolset_name": "computer",
                  "input": { "coordinate": [512, 742] }
                },
                {
                  "type": "tool_use",
                  "id": "toolu_017nJn3RgSCkTMwuZDb4uUov",
                  "name": "screenshot",
                  "toolset_name": "computer",
                  "input": {}
                }
              ],
              "stop_reason": "tool_use",
              "stop_sequence": null,
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/agents-and-tools/tool-use/browser-use-tool
        // composed: usage, no docs example
        'browser toolset' => <<<'JSON'
            {
              "id": "msg_01HCDu4XSTLzTAcodEQ58vDo",
              "type": "message",
              "role": "assistant",
              "model": "claude-opus-5-5",
              "content": [
                {
                  "type": "text",
                  "text": "I'll open the documentation and read the page to find the getting-started instructions."
                },
                {
                  "type": "tool_use",
                  "id": "toolu_01NRLabsLyVHZPKxbKvkfSMn",
                  "name": "navigate",
                  "toolset_name": "browser",
                  "input": { "url": "https://example.com/docs" }
                },
                {
                  "type": "tool_use",
                  "id": "toolu_01UvHU5cDyTZ2vXKf5wCkPqR",
                  "name": "read_page",
                  "toolset_name": "browser",
                  "input": { "filter": "interactive" }
                }
              ],
              "stop_reason": "tool_use",
              "stop_sequence": null,
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/preserved-thinking
        // composed: the envelope around input_transformations, no docs example
        'input transformations, thinking dropped' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "Hello" }],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 },
              "input_transformations": [
                {
                  "type": "thinking_dropped",
                  "path": "messages.3.content.0",
                  "reason": "model_binding_mismatch"
                }
              ]
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/preserved-thinking
        // composed: the envelope around input_transformations, no docs example
        'input transformations, mismatch allowed' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "Hello" }],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 },
              "input_transformations": [
                {
                  "type": "thinking_mismatch_allowed",
                  "path": "messages.1.content.0",
                  "reason": "prefix_binding_mismatch"
                }
              ]
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/preserved-thinking
        // composed: the envelope around input_transformations, no docs example
        'input transformations, two dropped' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "Hello" }],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 },
              "input_transformations": [
                {
                  "type": "thinking_dropped",
                  "path": "messages.2.content.0",
                  "reason": "prefix_binding_mismatch"
                },
                {
                  "type": "thinking_dropped",
                  "path": "messages.4.content.0",
                  "reason": "prefix_binding_mismatch"
                }
              ]
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/refusals-and-fallback
        'server-side fallback with trigger' => <<<'JSON'
            {
              "id": "msg_01XFUDYJgAACzvnptvVoYEL",
              "type": "message",
              "role": "assistant",
              "model": "claude-opus-4-8",
              "content": [
                {
                  "type": "fallback",
                  "from": { "model": "claude-fable-5" },
                  "to": { "model": "claude-opus-4-8" },
                  "trigger": { "type": "refusal", "category": "cyber" }
                },
                { "type": "text", "text": "Hi! How can I help you today?" }
              ],
              "stop_reason": "end_turn",
              "stop_details": null,
              "usage": {
                "input_tokens": 412,
                "output_tokens": 264,
                "cache_read_input_tokens": 0,
                "cache_creation_input_tokens": 0,
                "iterations": [
                  {
                    "type": "message",
                    "model": "claude-fable-5",
                    "input_tokens": 535,
                    "output_tokens": 0,
                    "cache_read_input_tokens": 0,
                    "cache_creation_input_tokens": 0
                  },
                  {
                    "type": "fallback_message",
                    "model": "claude-opus-4-8",
                    "input_tokens": 412,
                    "output_tokens": 264,
                    "cache_read_input_tokens": 0,
                    "cache_creation_input_tokens": 0
                  }
                ]
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/refusals-and-fallback
        'refusal' => <<<'JSON'
            {
              "id": "msg_01XFUDYJgAACzvnptvVoYEL",
              "type": "message",
              "role": "assistant",
              "model": "claude-fable-5",
              "content": [],
              "stop_reason": "refusal",
              "stop_details": {
                "type": "refusal",
                "category": "cyber",
                "explanation": "This request was declined because it could enable cyber harm."
              },
              "usage": {
                "input_tokens": 412,
                "output_tokens": 0
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/api/beta/messages/create (placeholder values)
        // composed: the envelope around stop_details and usage.fallback_credit, no docs example
        'fallback credit' => <<<'JSON'
            {
              "id": "msg_01XFUDYJgAACzvnptvVoYEL",
              "type": "message",
              "role": "assistant",
              "model": "claude-fable-5",
              "content": [],
              "stop_reason": "refusal",
              "stop_details": {
                "category": "cyber",
                "explanation": "This request was declined because it conflicts with Anthropic's Usage Policy.",
                "fallback_credit_token": "QW50aHJvcGljL0NsYXVkZQ==",
                "fallback_has_prefill_claim": true,
                "recommended_model": "claude-opus-4-8",
                "type": "refusal"
              },
              "usage": {
                "input_tokens": 412,
                "output_tokens": 0,
                "fallback_credit": {
                  "status": {
                    "type": "redeemed"
                  }
                }
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/context-editing
        // composed: the envelope around context_management, no docs example
        'context editing' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "Hello" }],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 },
              "context_management": {
                "applied_edits": [
                  {
                    "type": "clear_thinking_20251015",
                    "cleared_thinking_turns": 3,
                    "cleared_input_tokens": 15000
                  },
                  {
                    "type": "clear_tool_uses_20250919",
                    "cleared_tool_uses": 8,
                    "cleared_input_tokens": 50000
                  }
                ]
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/thinking
        // composed: the envelope around the content, no docs example
        'thinking progress updates' => <<<'JSON'
            {
              "id": "msg_019hiOHAEXQwq1PTeETNEBWe",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [
                {
                  "type": "thinking",
                  "thinking": "",
                  "signature": "EqMBCkYICxIM"
                },
                {
                  "type": "thinking",
                  "thinking": "Confirmed the retry path never refreshes the expired token. Editing auth.py to add the refresh call.",
                  "signature": "Es8CCkYICxIM"
                },
                {
                  "type": "tool_use",
                  "id": "toolu_01D7FLrfh4GYq7yT1ULFeyMV",
                  "name": "edit_file",
                  "input": { "path": "auth.py", "content": "" }
                }
              ],
              "stop_reason": "tool_use",
              "usage": { "input_tokens": 10, "output_tokens": 20 }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/cache-diagnostics
        // composed: model and stop_reason, no docs example
        'cache diagnostics' => <<<'JSON'
            {
              "id": "msg_01Xyz",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "" }],
              "stop_reason": "end_turn",
              "usage": {
                "input_tokens": 42,
                "cache_read_input_tokens": 0,
                "cache_creation_input_tokens": 41850,
                "output_tokens": 210
              },
              "diagnostics": {
                "cache_miss_reason": {
                  "type": "system_changed",
                  "cache_missed_input_tokens": 41850
                }
              }
            }
            JSON,
        // https://platform.claude.com/docs/en/build-with-claude/cache-diagnostics
        // composed: the envelope around diagnostics, no docs example
        'cache diagnostics not requested' => <<<'JSON'
            {
              "id": "msg_01Xyz",
              "type": "message",
              "role": "assistant",
              "model": "claude-sonnet-4-6",
              "content": [{ "type": "text", "text": "Hello" }],
              "stop_reason": "end_turn",
              "usage": { "input_tokens": 10, "output_tokens": 20 },
              "diagnostics": null
            }
            JSON,
    ];
}
