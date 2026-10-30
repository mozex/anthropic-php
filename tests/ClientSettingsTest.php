<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;

test('the settings report what the client sends, never its key', function () {
    $client = Anthropic::factory()
        ->withApiKey('sk-ant-secret')
        ->withBaseUrl('https://gateway.example.com')
        ->withApiVersion('2026-10-21')
        ->withHttpHeader('anthropic-beta', 'compact-2026-09-04')
        ->withWorkspaceId('wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->withMaxRetries(2)
        ->withTimeout(120)
        ->withConnectTimeout(5)
        ->make();

    expect($client->settings()->toArray())->toBe([
        'base_uri' => 'https://gateway.example.com/v1/',
        'api_version' => '2026-10-21',
        'betas' => ['compact-2026-09-04'],
        'workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy',
        'max_retries' => 2,
        'timeout' => 120.0,
        'connect_timeout' => 5.0,
    ])
        ->and(serialize($client->settings()))->not->toContain('sk-ant-secret');
});

test('the defaults show as the client applies them', function () {
    $settings = Anthropic::client('foo')->settings();

    expect($settings->baseUri)->toBe('https://api.anthropic.com/v1/')
        ->and($settings->apiVersion)->toBe('2023-06-01')
        ->and($settings->betas)->toBe([])
        ->and($settings->workspaceId)->toBeNull()
        ->and($settings->maxRetries)->toBe(0)
        ->and($settings->timeout)->toBeNull()
        ->and($settings->connectTimeout)->toBeNull();
});

test('the headers are read whatever their spelling', function () {
    $settings = Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpHeader('Anthropic-Beta', 'compact-2026-09-04')
        ->withHttpHeader('ANTHROPIC-VERSION', '2026-10-21')
        ->withHttpHeader('Anthropic-Workspace-Id', 'wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->make()
        ->settings();

    expect($settings->betas)->toBe(['compact-2026-09-04'])
        ->and($settings->apiVersion)->toBe('2026-10-21')
        ->and($settings->workspaceId)->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy');
});

test('the base URI is reported without the user info a proxy URL may carry', function (string $uri) {
    $settings = Anthropic::factory()->withApiKey('foo')->withBaseUri($uri)->make()->settings();

    expect($settings->baseUri)->toBe('https://proxy.example.com/v1/')
        ->and(serialize($settings))->not->toContain('secret');
})->with([
    // composed: proxy URLs with credentials in them
    'a user and password' => ['https://user:secret@proxy.example.com/v1'],
    'a password with an unencoded @' => ['https://user:sec@ret-secret@proxy.example.com/v1'],
]);

test('a withOptions() client reports its own betas, version and retries', function () {
    $client = Anthropic::factory()->withApiKey('foo')->withHttpHeader('anthropic-beta', 'compact-2026-09-04')->make();

    $settings = $client->withOptions(['betas' => ['skills-2025-10-02'], 'api_version' => '2026-10-21', 'max_retries' => 3, 'workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy'])->settings();

    expect($settings->betas)->toBe(['compact-2026-09-04', 'skills-2025-10-02'])
        ->and($settings->workspaceId)->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->and($settings->apiVersion)->toBe('2026-10-21')
        ->and($settings->maxRetries)->toBe(3)
        ->and($client->settings()->betas)->toBe(['compact-2026-09-04']);
});

test('the timeouts of your own HTTP client aren\'t known', function () {
    $settings = Anthropic::factory()->withApiKey('foo')->withHttpClient(new GuzzleClient(['timeout' => 30]))->make()->settings();

    expect($settings->timeout)->toBeNull()
        ->and($settings->connectTimeout)->toBeNull();
});
