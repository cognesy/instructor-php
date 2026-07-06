<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequestBody;

it('throws explicit exception when array body cannot be json encoded', function () {
    $resource = fopen('php://memory', 'rb');

    expect(fn() => new HttpRequestBody(['stream' => $resource]))
        ->toThrow(InvalidArgumentException::class, 'Failed to encode request body as JSON');

    if (is_resource($resource)) {
        fclose($resource);
    }
});

it('keeps decoded request body stable across repeated calls', function () {
    $body = new HttpRequestBody(['message' => 'hello', 'count' => 2]);

    expect($body->toArray())->toBe(['message' => 'hello', 'count' => 2])
        ->and($body->toArray())->toBe(['message' => 'hello', 'count' => 2]);
});

it('refreshes decoded request body when public body string changes', function () {
    $body = new HttpRequestBody(['message' => 'hello']);

    expect($body->toArray())->toBe(['message' => 'hello']);

    $body->body = '{"message":"updated"}';

    expect($body->toArray())->toBe(['message' => 'updated']);
});
