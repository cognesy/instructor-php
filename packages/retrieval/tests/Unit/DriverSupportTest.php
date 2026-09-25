<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Retrieval\Drivers\Support\BoundedHttpClient;
use Cognesy\Retrieval\Drivers\Support\DocumentCodec;

it('roundtrips distinct typed ids through backend safe keys and UUIDs', function () {
    $int = DocumentCodec::identity(7);
    $string = DocumentCodec::identity('7');

    expect(DocumentCodec::key(7))->not->toBe(DocumentCodec::key('7'))
        ->and(DocumentCodec::uuid('arbitrary id'))->toMatch('/^[0-9a-f-]{36}$/')
        ->and(DocumentCodec::restoreId($int))->toBe(7)
        ->and(DocumentCodec::restoreId($string))->toBe('7');
});

it('encodes typed metadata and deterministic equality tokens', function () {
    $metadata = ['tenant' => 'a', 'enabled' => true, 'nested' => ['x' => 1]];
    $encoded = DocumentCodec::encodeMetadata($metadata);
    $tokens = DocumentCodec::metadataTokens($metadata);

    expect(DocumentCodec::decodeMetadata($encoded))->toBe($metadata)
        ->and(DocumentCodec::encodeMetadata([]))->toBe('{}')
        ->and(DocumentCodec::decodeMetadata(DocumentCodec::encodeMetadata([])))->toBe([])
        ->and($tokens)->toContain(DocumentCodec::metadataToken('enabled', true))
        ->and(DocumentCodec::metadataToken('enabled', true))
        ->not->toBe(DocumentCodec::metadataToken('enabled', 1));
});

it('reads bounded JSON and JSONL responses without leaking failure bodies', function () {
    $transport = new DriverSupportFakeTransport([
        [200, '{"ok":true}'],
        [200, "{\"success\":true}\n{\"success\":false}"],
        [500, '{"secret":"credential"}'],
    ]);
    $http = new BoundedHttpClient(HttpClient::fromDriver($transport), 'Fixture', 128);

    expect($http->send('GET', 'http://fixture')->json('Fixture'))->toBe(['ok' => true])
        ->and($http->send('GET', 'http://fixture')->jsonLines('Fixture'))->toHaveCount(2)
        ->and(fn () => $http->send('GET', 'http://fixture'))
        ->toThrow(RuntimeException::class, 'Fixture request failed with HTTP 500');
});

it('stops reading a streamed response at the configured byte limit', function () {
    $transport = new DriverSupportFakeTransport([[200, str_repeat('x', 40)]]);
    $http = new BoundedHttpClient(HttpClient::fromDriver($transport), 'Fixture', 20);

    expect(fn () => $http->send('GET', 'http://fixture'))
        ->toThrow(RuntimeException::class, 'exceeds configured byte limit');
});

final class DriverSupportFakeTransport implements CanHandleHttpRequest
{
    /** @param list<array{0: int, 1: string}> $responses */
    public function __construct(private array $responses) {}

    public function handle(HttpRequest $request): HttpResponse
    {
        [$status, $body] = array_shift($this->responses) ?? [200, ''];

        return HttpResponse::streamingFromIterable($status, [], str_split($body, 7));
    }
}
