<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Extras\Support\RecordReplay\StreamedRequestRecord;

/**
 * Chunks are the canonical body store for streamed records.
 * getResponseBody() is a derived view; legacy records that stored the
 * body twice (response.body + chunks) must still load and normalize
 * to the canonical single representation on re-save.
 */

function canonicalBodyRequest(): HttpRequest {
    return new HttpRequest(
        'https://api.example.com/data',
        'POST',
        ['Accept' => 'application/json'],
        '{"q":1}',
        [],
    );
}

test('non-streamed interaction stores body only as chunks', function () {
    $response = MockHttpResponseFactory::success(body: '{"id":123}');

    $record = StreamedRequestRecord::fromStreamedInteraction(canonicalBodyRequest(), $response);
    $json = json_decode($record->toJson(), true);

    expect($json['response'])->not->toHaveKey('body');
    expect($json['chunks'])->toBe(['{"id":123}']);
    expect($record->getChunks())->toBe(['{"id":123}']);
    expect($record->getResponseBody())->toBe('{"id":123}');
});

test('non-streamed interaction with empty body stores no chunks', function () {
    $response = MockHttpResponseFactory::success(body: '');

    $record = StreamedRequestRecord::fromStreamedInteraction(canonicalBodyRequest(), $response);
    $json = json_decode($record->toJson(), true);

    expect($json['response'])->not->toHaveKey('body');
    expect($json['chunks'])->toBe([]);
    expect($record->getResponseBody())->toBe('');
});

test('non-streamed record replays its full body via toResponse', function () {
    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    $record = StreamedRequestRecord::fromStreamedInteraction(canonicalBodyRequest(), $response);

    expect($record->toResponse(isStreaming: false)->body())->toBe('{"id":123}');
    expect(implode('', iterator_to_array($record->toResponse(isStreaming: true)->stream())))->toBe('{"id":123}');
});

test('loads legacy double-representation records and normalizes them on re-save', function () {
    $legacyJson = json_encode([
        'request' => ['url' => 'https://api.example.com/data', 'method' => 'POST', 'headers' => [], 'body' => '{"q":1}', 'options' => []],
        'response' => ['statusCode' => 200, 'headers' => [], 'body' => '{"id":123}'],
        'chunks' => ['{"id":123}'],
    ]);

    $record = StreamedRequestRecord::fromJson($legacyJson);

    expect($record)->not->toBeNull();
    expect($record->getResponseBody())->toBe('{"id":123}');
    expect($record->getChunks())->toBe(['{"id":123}']);

    // re-save drops the redundant body — chunks are canonical
    $resaved = json_decode($record->toJson(), true);
    expect($resaved['response'])->not->toHaveKey('body');
    expect($resaved['chunks'])->toBe(['{"id":123}']);

    // and the normalized record round-trips identically
    $reloaded = StreamedRequestRecord::fromJson($record->toJson());
    expect($reloaded->getResponseBody())->toBe('{"id":123}');
});

test('preserves a legacy body that diverges from chunks', function () {
    $legacyJson = json_encode([
        'request' => ['url' => 'https://api.example.com/data', 'method' => 'POST', 'headers' => [], 'body' => '', 'options' => []],
        'response' => ['statusCode' => 200, 'headers' => [], 'body' => 'hand-edited'],
        'chunks' => ['{"id":123}'],
    ]);

    $record = StreamedRequestRecord::fromJson($legacyJson);

    // legacy explicit body wins in the derived view
    expect($record->getResponseBody())->toBe('hand-edited');

    // divergent body carries real info — kept on re-save
    $resaved = json_decode($record->toJson(), true);
    expect($resaved['response']['body'] ?? null)->toBe('hand-edited');

    $reloaded = StreamedRequestRecord::fromJson($record->toJson());
    expect($reloaded->getResponseBody())->toBe('hand-edited');
});
