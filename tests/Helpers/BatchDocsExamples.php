<?php

declare(strict_types=1);

/**
 * Message Batches responses from the docs, as JSON text. Create, retrieve and cancel return the same
 * object in the reference, so it is listed once.
 *
 * @return array<string, string>
 */
function batchesDocsExamples(): array
{
    return [
        // https://platform.claude.com/docs/en/api/messages/batches/retrieve
        'batch' => <<<'JSON'
            {
              "id": "msgbatch_013Zva2CMHLNnXjNJJKqJ2EF",
              "archived_at": "2024-08-20T18:37:24.100435Z",
              "cancel_initiated_at": "2024-08-20T18:37:24.100435Z",
              "created_at": "2024-08-20T18:37:24.100435Z",
              "ended_at": "2024-08-20T18:37:24.100435Z",
              "expires_at": "2024-08-20T18:37:24.100435Z",
              "processing_status": "in_progress",
              "request_counts": {
                "canceled": 10,
                "errored": 30,
                "expired": 10,
                "processing": 100,
                "succeeded": 50
              },
              "results_url": "https://api.anthropic.com/v1/messages/batches/msgbatch_013Zva2CMHLNnXjNJJKqJ2EF/results",
              "type": "message_batch"
            }
            JSON,
        // https://platform.claude.com/docs/en/api/messages/batches/list
        'list' => <<<'JSON'
            {
              "data": [
                {
                  "id": "msgbatch_013Zva2CMHLNnXjNJJKqJ2EF",
                  "archived_at": "2024-08-20T18:37:24.100435Z",
                  "cancel_initiated_at": "2024-08-20T18:37:24.100435Z",
                  "created_at": "2024-08-20T18:37:24.100435Z",
                  "ended_at": "2024-08-20T18:37:24.100435Z",
                  "expires_at": "2024-08-20T18:37:24.100435Z",
                  "processing_status": "in_progress",
                  "request_counts": {
                    "canceled": 10,
                    "errored": 30,
                    "expired": 10,
                    "processing": 100,
                    "succeeded": 50
                  },
                  "results_url": "https://api.anthropic.com/v1/messages/batches/msgbatch_013Zva2CMHLNnXjNJJKqJ2EF/results",
                  "type": "message_batch"
                }
              ],
              "first_id": "first_id",
              "has_more": true,
              "last_id": "last_id"
            }
            JSON,
        // https://platform.claude.com/docs/en/api/messages/batches/delete
        'deleted' => <<<'JSON'
            {
              "id": "msgbatch_013Zva2CMHLNnXjNJJKqJ2EF",
              "type": "message_batch_deleted"
            }
            JSON,
    ];
}

/**
 * Batch result lines (JSONL). The reference has no example; the batch processing guide shows two
 * succeeded lines. The other result types are composed around the documented shapes.
 *
 * @return array<string, string>
 */
function batchResultsDocsExamples(): array
{
    return [
        // https://platform.claude.com/docs/en/build-with-claude/batch-processing
        'succeeded' => '{"custom_id":"my-second-request","result":{"type":"succeeded","message":{"id":"msg_014VwiXbi91y3JMjcpyGBHX5","type":"message","role":"assistant","model":"claude-opus-5-5","content":[{"type":"text","text":"Hello again! It\'s nice to see you. How can I assist you today? Is there anything specific you\'d like to chat about or any questions you have?"}],"stop_reason":"end_turn","stop_sequence":null,"usage":{"input_tokens":11,"output_tokens":36}}}}',
        'succeeded (second)' => '{"custom_id":"my-first-request","result":{"type":"succeeded","message":{"id":"msg_01FqfsLoHwgeFbguDgpz48m7","type":"message","role":"assistant","model":"claude-opus-5-5","content":[{"type":"text","text":"Hello! How can I assist you today? Feel free to ask me any questions or let me know if there\'s anything you\'d like to chat about."}],"stop_reason":"end_turn","stop_sequence":null,"usage":{"input_tokens":10,"output_tokens":34}}}}',
        // composed: custom_id and result envelope; the error is the example of https://platform.claude.com/docs/en/api/errors
        'errored' => '{"custom_id":"my-third-request","result":{"type":"errored","error":{"type":"error","error":{"type":"not_found_error","message":"The requested resource could not be found."},"request_id":"req_011CSHoEeqs5C35K2UUqR7Fy"}}}',
        // composed: custom_id and result envelope; the error is the spend-cap example of https://platform.claude.com/docs/en/api/rate-limits
        'errored (spend cap)' => '{"custom_id":"my-fourth-request","result":{"type":"errored","error":{"type":"error","error":{"type":"rate_limit_error","message":"You have reached your API usage limits: your organization has crossed its monthly API usage threshold, set based on your organization\'s API tier. You will regain access on 2026-09-01 at 00:00 UTC.","details":{"error_code":"enforced_spend_limit_reached"}},"request_id":"req_018EeWyXxfu5pfWkrYcMdjWG"}}}',
        // composed: no docs example of a canceled or expired line
        'canceled' => '{"custom_id":"my-fifth-request","result":{"type":"canceled"}}',
        'expired' => '{"custom_id":"my-sixth-request","result":{"type":"expired"}}',
    ];
}
