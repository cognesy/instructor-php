<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;

test('pending response executes sync and stream modes independently', function() {
    $driver = new MockHttpDriver();
    $driver->expect()
        ->get('https://api.example.com/messages')
        ->withStream(false)
        ->times(1)
        ->replyText('sync-body');
    $driver->expect()
        ->get('https://api.example.com/messages')
        ->withStream(true)
        ->times(1)
        ->replyStreamChunks(['chunk-1', 'chunk-2']);

    $client = (new HttpClientBuilder())->withDriver($driver)->create();
    $pending = $client->withRequest(new HttpRequest(
        'https://api.example.com/messages',
        'GET',
        [],
        '',
        [],
    ));

    expect($pending->content())->toBe('sync-body');
    expect(iterator_to_array($pending->stream()))->toBe(['chunk-1', 'chunk-2']);

    $received = $driver->getReceivedRequests();
    expect($received)->toHaveCount(2);
    expect($received[0]->isStreamed())->toBeFalse();
    expect($received[1]->isStreamed())->toBeTrue();
});

test('pending response can stream first and still resolve sync content', function() {
    $driver = new MockHttpDriver();
    $driver->expect()
        ->get('https://api.example.com/messages')
        ->withStream(true)
        ->times(1)
        ->replyStreamChunks(['chunk-1', 'chunk-2']);
    $driver->expect()
        ->get('https://api.example.com/messages')
        ->withStream(false)
        ->times(1)
        ->replyText('sync-body');

    $client = (new HttpClientBuilder())->withDriver($driver)->create();
    $pending = $client->withRequest(new HttpRequest(
        'https://api.example.com/messages',
        'GET',
        [],
        '',
        [],
    ));

    expect(iterator_to_array($pending->stream()))->toBe(['chunk-1', 'chunk-2']);
    expect($pending->content())->toBe('sync-body');

    $received = $driver->getReceivedRequests();
    expect($received)->toHaveCount(2);
    expect($received[0]->isStreamed())->toBeTrue();
    expect($received[1]->isStreamed())->toBeFalse();
});

test('pending response reuses cached sync response for sync accessors', function() {
    $driver = new MockHttpDriver();
    $driver->expect()
        ->get('https://api.example.com/messages')
        ->withStream(false)
        ->times(1)
        ->replyText('sync-body');

    $client = (new HttpClientBuilder())->withDriver($driver)->create();
    $pending = $client->withRequest(new HttpRequest(
        'https://api.example.com/messages',
        'GET',
        [],
        '',
        [],
    ));

    expect($pending->statusCode())->toBe(200);
    expect($pending->content())->toBe('sync-body');
    expect($pending->get()->statusCode())->toBe(200);
    expect($driver->getReceivedRequests())->toHaveCount(1);
});
