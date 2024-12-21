<?php

use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\CreateStreamedResponseContentBlockStart;
use Anthropic\Responses\Messages\CreateStreamedResponseDelta;
use Anthropic\Responses\Messages\CreateStreamedResponseMessage;
use Anthropic\Responses\Messages\CreateStreamedResponseUsage;

test('from first chunk', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamFirstChunk());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('message_start')
        ->index->toBeNull()
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBeNull()
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBe('msg_01YS82gyNJHzAN1xVt2ymmTN')
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBeNull()
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBe(10);
});

test('from content block start chunk', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamContentBlockStartChunk());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('content_block_start')
        ->index->toBe(0)
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBeNull()
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBeNull()
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBe('text')
        ->content_block_start->text->toBe('')
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBeNull();
});

test('from content block start chunk with tool calls', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamToolCallsContentBlockStartChunk());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('content_block_start')
        ->index->toBe(1)
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBeNull()
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBeNull()
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBe('tool_use')
        ->content_block_start->id->toBe('toolu_01T1x8fJ34qAma2tNTrN7Up1')
        ->content_block_start->name->toBe('get_weather')
        ->content_block_start->input->toBe([])
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBeNull();
});

test('from content chunk', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamContentChunk());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('content_block_delta')
        ->index->toBe(0)
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBe('Hello')
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBeNull()
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBeNull()
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBeNull();
});

test('from last chunk', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamLastChunk());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('message_delta')
        ->index->toBeNull()
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBeNull()
        ->delta->stop_reason->toBe('end_turn')
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBeNull()
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBeNull()
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBeNull()
        ->usage->outputTokens->toBe(15);
});

test('as array accessible', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamFirstChunk());

    expect($completion['message']['id'])->toBe('msg_01YS82gyNJHzAN1xVt2ymmTN');
});

test('to array', function () {
    $completion = CreateStreamedResponse::from(messagesCompletionStreamFirstChunk());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe([
            'type' => 'message_start',
            'index' => null,
            'delta' => [
                'type' => null,
                'text' => null,
                'stop_reason' => null,
                'stop_sequence' => null,
            ],
            'message' => [
                'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [],
                'model' => 'claude-3-haiku-20240307',
                'stop_reason' => null,
                'stop_sequence' => null,
            ],
            'content_block_start' => [
                'id' => null,
                'type' => null,
                'text' => null,
                'name' => null,
                'input' => null,
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 1,
            ],
        ]);
});

test('fake', function () {
    $response = CreateStreamedResponse::fake();

    expect($response->getIterator()->current())
        ->message->id->toBe('msg_01DY6yoXeLT7DXqxiVSSJbha')
        ->content_block_start->id->toBeNull();
});

test('fake with override', function () {
    $response = CreateStreamedResponse::fake(messagesCompletionStream());

    expect($response->getIterator()->current())
        ->message->id->toBe('msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY')
        ->content_block_start->id->toBeNull();
});
