<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Utils\Metadata;

it('round-trips HttpRequest serialization without losing metadata or core request fields', function () {
    $request = new HttpRequest(
        url: 'https://example.test/v1/messages',
        method: 'POST',
        headers: ['Authorization' => 'Bearer token', 'X-Test' => 'yes'],
        body: ['message' => 'hello', 'count' => 2],
        options: ['timeout' => 30, 'stream' => false],
        metadata: Metadata::fromArray(['traceId' => 'trace-123', 'source' => 'test']),
    );

    $hydrated = HttpRequest::fromArray($request->toArray());

    expect($hydrated->url())->toBe($request->url())
        ->and($hydrated->method())->toBe($request->method())
        ->and($hydrated->headers())->toBe($request->headers())
        ->and($hydrated->options())->toBe($request->options())
        ->and($hydrated->metadata->toArray())->toBe($request->metadata->toArray());
});

it('preserves plain-text request body across HttpRequest toArray/fromArray round-trip', function () {
    $request = new HttpRequest(
        url: 'https://example.test/v1/messages',
        method: 'POST',
        headers: ['Content-Type' => 'text/plain'],
        body: 'plain-text-payload',
        options: ['timeout' => 10],
        metadata: Metadata::fromArray(['traceId' => 'trace-plain']),
    );

    $hydrated = HttpRequest::fromArray($request->toArray());

    expect($hydrated->body()->toString())->toBe('plain-text-payload');
});

it('keeps JSON-array request body compatible across HttpRequest toArray/fromArray round-trip', function () {
    $request = new HttpRequest(
        url: 'https://example.test/v1/messages',
        method: 'POST',
        headers: ['Content-Type' => 'application/json'],
        body: ['a' => 1, 'b' => 'two'],
        options: ['timeout' => 10],
    );

    $hydrated = HttpRequest::fromArray($request->toArray());

    expect($hydrated->body()->toArray())->toBe(['a' => 1, 'b' => 'two']);
});
