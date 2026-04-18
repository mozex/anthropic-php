<?php

use Anthropic\Responses\Files\FileResponseScope;

test('from', function () {
    $result = FileResponseScope::from([
        'id' => 'session_01AbCdEfGhIjKlMnOpQrStUv',
        'type' => 'session',
    ]);

    expect($result)
        ->toBeInstanceOf(FileResponseScope::class)
        ->id->toBe('session_01AbCdEfGhIjKlMnOpQrStUv')
        ->type->toBe('session');
});

test('to array', function () {
    $result = FileResponseScope::from([
        'id' => 'session_01AbCdEfGhIjKlMnOpQrStUv',
        'type' => 'session',
    ]);

    expect($result->toArray())
        ->toBe([
            'id' => 'session_01AbCdEfGhIjKlMnOpQrStUv',
            'type' => 'session',
        ]);
});
