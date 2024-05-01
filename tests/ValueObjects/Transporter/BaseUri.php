<?php

use Anthropic\ValueObjects\Transporter\BaseUri;

it('can be created from a string', function () {
    $baseUri = BaseUri::from('api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('https://api.anthropic.com/v1/');
});

it('can be created from a string with http protocol', function () {
    $baseUri = BaseUri::from('http://api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('http://api.anthropic.com/v1/');
});

it('can be created from a string with https protocol', function () {
    $baseUri = BaseUri::from('https://api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('https://api.anthropic.com/v1/');
});
