<?php

declare(strict_types=1);

/**
 * Models API responses from the reference, as JSON text. The reference examples carry placeholders
 * (`"max_tokens": 0`, a 2019 `deprecated_at` on an active model, `"first_id": "first_id"`); they are kept.
 *
 * @return array<string, string>
 */
function modelsDocsExamples(): array
{
    return [
        // https://platform.claude.com/docs/en/api/models/retrieve
        'retrieve' => <<<'JSON'
            {
              "id": "claude-opus-5-5",
              "capabilities": {
                "batch": {
                  "supported": true
                },
                "citations": {
                  "supported": true
                },
                "code_execution": {
                  "supported": true
                },
                "context_management": {
                  "clear_thinking_20251015": {
                    "supported": true
                  },
                  "clear_tool_uses_20250919": {
                    "supported": true
                  },
                  "compact_20260112": {
                    "supported": true
                  },
                  "supported": true
                },
                "effort": {
                  "high": {
                    "supported": true
                  },
                  "low": {
                    "supported": true
                  },
                  "max": {
                    "supported": true
                  },
                  "medium": {
                    "supported": true
                  },
                  "supported": true,
                  "xhigh": {
                    "supported": true
                  }
                },
                "image_input": {
                  "supported": true
                },
                "pdf_input": {
                  "supported": true
                },
                "server_tools": {
                  "code_execution": {
                    "supported": true
                  },
                  "supported": true,
                  "web_search": {
                    "supported": true
                  }
                },
                "structured_outputs": {
                  "supported": true
                },
                "thinking": {
                  "supported": true,
                  "types": {
                    "adaptive": {
                      "supported": true
                    },
                    "between_tools": {
                      "supported": true
                    },
                    "disabled": {
                      "supported": true
                    },
                    "enabled": {
                      "supported": true
                    }
                  }
                }
              },
              "created_at": "2026-09-21T00:00:00Z",
              "deprecated_at": "2019-12-27T18:11:19.117Z",
              "display_name": "Claude Opus 5.5",
              "lifecycle": "active",
              "line": "opus",
              "max_input_tokens": 0,
              "max_tokens": 0,
              "retires_at": "2019-12-27T18:11:19.117Z",
              "type": "model"
            }
            JSON,
        // https://platform.claude.com/docs/en/api/beta/models/retrieve
        'retrieve (beta)' => <<<'JSON'
            {
              "id": "claude-opus-5-5",
              "allowed_fallback_models": [
                "string"
              ],
              "capabilities": {
                "batch": {
                  "supported": true
                },
                "citations": {
                  "supported": true
                },
                "code_execution": {
                  "supported": true
                },
                "compaction": {
                  "summarize": {
                    "supported": true
                  },
                  "supported": true
                },
                "context_management": {
                  "clear_thinking_20251015": {
                    "supported": true
                  },
                  "clear_tool_uses_20250919": {
                    "supported": true
                  },
                  "compact_20260112": {
                    "supported": true
                  },
                  "supported": true
                },
                "effort": {
                  "high": {
                    "supported": true
                  },
                  "low": {
                    "supported": true
                  },
                  "max": {
                    "supported": true
                  },
                  "medium": {
                    "supported": true
                  },
                  "supported": true,
                  "xhigh": {
                    "supported": true
                  }
                },
                "image_input": {
                  "supported": true
                },
                "pdf_input": {
                  "supported": true
                },
                "server_tools": {
                  "code_execution": {
                    "supported": true
                  },
                  "supported": true,
                  "web_search": {
                    "supported": true
                  }
                },
                "structured_outputs": {
                  "supported": true
                },
                "thinking": {
                  "supported": true,
                  "types": {
                    "adaptive": {
                      "supported": true
                    },
                    "between_tools": {
                      "supported": true
                    },
                    "disabled": {
                      "supported": true
                    },
                    "enabled": {
                      "supported": true
                    }
                  }
                }
              },
              "created_at": "2026-09-21T00:00:00Z",
              "deprecated_at": "2019-12-27T18:11:19.117Z",
              "display_name": "Claude Opus 5.5",
              "lifecycle": "active",
              "line": "opus",
              "max_input_tokens": 0,
              "max_tokens": 0,
              "retires_at": "2019-12-27T18:11:19.117Z",
              "type": "model"
            }
            JSON,
    ];
}

/**
 * https://platform.claude.com/docs/en/api/models/list
 */
function modelsListDocsExample(): string
{
    return <<<'JSON'
        {
          "data": [
            {
              "id": "claude-opus-5-5",
              "capabilities": {
                "batch": {
                  "supported": true
                },
                "citations": {
                  "supported": true
                },
                "code_execution": {
                  "supported": true
                },
                "context_management": {
                  "clear_thinking_20251015": {
                    "supported": true
                  },
                  "clear_tool_uses_20250919": {
                    "supported": true
                  },
                  "compact_20260112": {
                    "supported": true
                  },
                  "supported": true
                },
                "effort": {
                  "high": {
                    "supported": true
                  },
                  "low": {
                    "supported": true
                  },
                  "max": {
                    "supported": true
                  },
                  "medium": {
                    "supported": true
                  },
                  "supported": true,
                  "xhigh": {
                    "supported": true
                  }
                },
                "image_input": {
                  "supported": true
                },
                "pdf_input": {
                  "supported": true
                },
                "server_tools": {
                  "code_execution": {
                    "supported": true
                  },
                  "supported": true,
                  "web_search": {
                    "supported": true
                  }
                },
                "structured_outputs": {
                  "supported": true
                },
                "thinking": {
                  "supported": true,
                  "types": {
                    "adaptive": {
                      "supported": true
                    },
                    "between_tools": {
                      "supported": true
                    },
                    "disabled": {
                      "supported": true
                    },
                    "enabled": {
                      "supported": true
                    }
                  }
                }
              },
              "created_at": "2026-09-21T00:00:00Z",
              "deprecated_at": "2019-12-27T18:11:19.117Z",
              "display_name": "Claude Opus 5.5",
              "lifecycle": "active",
              "line": "opus",
              "max_input_tokens": 0,
              "max_tokens": 0,
              "retires_at": "2019-12-27T18:11:19.117Z",
              "type": "model"
            }
          ],
          "first_id": "first_id",
          "has_more": true,
          "last_id": "last_id"
        }
        JSON;
}
