<?php

use Anthropic\Responses\Images\CreateResponse;
use Anthropic\Responses\Images\CreateResponseData;
use Anthropic\Responses\Images\EditResponse;
use Anthropic\Responses\Images\EditResponseData;
use Anthropic\Responses\Images\VariationResponse;
use Anthropic\Responses\Images\VariationResponseData;
use Anthropic\Responses\Meta\MetaInformation;

test('create', function () {
    $client = mockClient('POST', 'images/generations', [
        'prompt' => 'A cute baby sea otter',
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ], \Anthropic\ValueObjects\Transporter\Response::from(imageCreateWithUrl(), metaHeaders()));

    $result = $client->images()->create([
        'prompt' => 'A cute baby sea otter',
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->created->toBe(1664136088)
        ->data->toBeArray()->toHaveCount(1)
        ->data->each->toBeInstanceOf(CreateResponseData::class);

    expect($result->data[0])
        ->url->toBe('https://openai.com/image.png');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('edit', function () {
    $client = mockClient('POST', 'images/edits', [
        'image' => fileResourceResource(),
        'mask' => fileResourceResource(),
        'prompt' => 'A sunlit indoor lounge area with a pool containing a flamingo',
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ], \Anthropic\ValueObjects\Transporter\Response::from(imageEditWithUrl(), metaHeaders()), validateParams: false);

    $result = $client->images()->edit([
        'image' => fileResourceResource(),
        'mask' => fileResourceResource(),
        'prompt' => 'A sunlit indoor lounge area with a pool containing a flamingo',
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ]);

    expect($result)
        ->toBeInstanceOf(EditResponse::class)
        ->created->toBe(1664136088)
        ->data->toBeArray()->toHaveCount(1)
        ->data->each->toBeInstanceOf(EditResponseData::class);

    expect($result->data[0])
        ->url->toBe('https://openai.com/image.png');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('variation', function () {
    $client = mockClient('POST', 'images/variations', [
        'image' => fileResourceResource(),
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ], \Anthropic\ValueObjects\Transporter\Response::from(imageVariationWithUrl(), metaHeaders()), validateParams: false);

    $result = $client->images()->variation([
        'image' => fileResourceResource(),
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ]);

    expect($result)
        ->toBeInstanceOf(VariationResponse::class)
        ->created->toBe(1664136088)
        ->data->toBeArray()->toHaveCount(1)
        ->data->each->toBeInstanceOf(VariationResponseData::class);

    expect($result->data[0])
        ->url->toBe('https://openai.com/image.png');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});
