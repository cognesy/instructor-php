<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteCorruptException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteExhaustedException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteMismatchException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteSerializationException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\LegacyCassetteException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\UnsupportedCassetteVersionException;
use Cognesy\Http\Extras\Support\RecordReplay\FilesystemCassetteStore;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\RecordReplayPolicy;
use Cognesy\Http\Stream\IterableStream;

beforeEach(function () {
    $this->directory = sys_get_temp_dir() . '/http_cassette_store_' . uniqid('', true);
});

afterEach(function () {
    $remove = function (string $directory) use (&$remove): void {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . '/' . $name;
            if (is_dir($path)) {
                $remove($path);
                continue;
            }
            unlink($path);
        }
        rmdir($directory);
    };
    $remove($this->directory);
});

function cassetteRequest(string $url = 'https://api.example.test/items', bool $streamed = false): HttpRequest {
    return new HttpRequest($url, 'POST', ['Accept' => 'application/octet-stream'], "\xFFrequest", [
        'stream' => $streamed,
    ]);
}

test('filesystem store writes a typed manifest and round-trips binary sync bodies', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest();
    $response = HttpResponse::sync(200, ['Content-Type' => 'application/octet-stream'], "\xFF\x00response\x80");

    $store->record($request, $response);
    $replayed = $store->replay($request);

    expect(file_exists($this->directory . '/cassette.json'))->toBeTrue()
        ->and(file_exists($this->directory . '/interactions/000001/interaction.json'))->toBeTrue()
        ->and(file_exists($this->directory . '/interactions/000001/request.body'))->toBeTrue()
        ->and(file_exists($this->directory . '/interactions/000001/response.body'))->toBeTrue()
        ->and($replayed?->body())->toBe("\xFF\x00response\x80");
});

test('filesystem store keeps streamed chunks binary-safe and replayable', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest(streamed: true);
    $chunks = ["\xFFfirst", "second\x00", "third\x80"];
    $response = HttpResponse::streaming(206, ['Content-Type' => 'application/octet-stream'], new IterableStream($chunks));

    $recorded = $store->record($request, $response);
    expect(iterator_to_array($recorded->stream()))->toBe($chunks);

    $replayed = $store->replay($request);
    expect($replayed)->not->toBeNull()
        ->and(implode('', iterator_to_array($replayed->stream())))->toBe(implode('', $chunks))
        ->and(file_exists($this->directory . '/interactions/000001/response.chunks.ndjson'))->toBeTrue();
});

test('abandoned streamed writes remove reservations and publish nothing', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $response = $store->record(
        cassetteRequest(streamed: true),
        HttpResponse::streaming(200, [], new IterableStream(['first', 'second'])),
    );

    foreach ($response->stream() as $_chunk) {
        break;
    }

    expect(glob($this->directory . '/interactions/[0-9]*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.reserved-*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.tmp-*'))->toBe([]);
});

test('filesystem store reserves distinct sequence directories', function () {
    $first = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $second = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest();

    $first->record($request, HttpResponse::sync(200, [], 'first'));
    $second->record($request, HttpResponse::sync(201, [], 'second'));

    expect(is_dir($this->directory . '/interactions/000001'))->toBeTrue()
        ->and(is_dir($this->directory . '/interactions/000002'))->toBeTrue();
});

test('filesystem store replays repeated and interleaved interactions in order', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $requestA = cassetteRequest('https://api.example.test/a');
    $requestB = cassetteRequest('https://api.example.test/b');

    $store->record($requestA, HttpResponse::sync(200, [], 'a-first'));
    $store->record($requestB, HttpResponse::sync(200, [], 'b-only'));
    $store->record($requestA, HttpResponse::sync(200, [], 'a-second'));

    $replay = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );

    expect($replay->replay($requestA)?->body())->toBe('a-first')
        ->and($replay->replay($requestB)?->body())->toBe('b-only')
        ->and($replay->replay($requestA)?->body())->toBe('a-second')
        ->and(fn () => $replay->replay($requestA))->toThrow(CassetteExhaustedException::class);
});

test('a replay mismatch does not consume the next interaction', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $expected = cassetteRequest('https://api.example.test/expected');
    $actual = cassetteRequest('https://api.example.test/actual');
    $store->record($expected, HttpResponse::sync(200, [], 'expected'));

    $replay = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );

    expect(fn () => $replay->replay($actual))->toThrow(CassetteMismatchException::class)
        ->and($replay->replay($expected)?->body())->toBe('expected');
});

test('stream recording yields the first chunk before the source produces the second', function () {
    $state = (object) ['yielded' => 0];
    $source = (function () use ($state): Generator {
        $state->yielded++;
        yield 'first';
        $state->yielded++;
        yield 'second';
    })();
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $response = $store->record(
        cassetteRequest(streamed: true),
        HttpResponse::streaming(200, [], new IterableStream($source)),
    );
    $stream = $response->stream();

    expect($stream->current())->toBe('first')
        ->and($state->yielded)->toBe(1);
    $stream->next();
    expect($stream->current())->toBe('second')
        ->and($state->yielded)->toBe(2);
    $stream->next();
    expect($stream->valid())->toBeFalse();
});

test('100K streamed chunks replay with bounded memory and preserved order', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest(streamed: true);
    $chunks = static function (): Generator {
        for ($index = 0; $index < 100_000; $index++) {
            yield 'x';
        }
    };
    $recorded = $store->record(
        $request,
        HttpResponse::streaming(200, [], new IterableStream($chunks())),
    );
    $recordHash = hash_init('sha256');
    foreach ($recorded->stream() as $chunk) {
        hash_update($recordHash, $chunk);
    }

    $memoryBeforeReplay = memory_get_usage(true);
    $replay = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $replayed = $replay->replay($request);
    $replayHash = hash_init('sha256');
    $replayedChunks = 0;
    foreach ($replayed?->stream() ?? [] as $chunk) {
        hash_update($replayHash, $chunk);
        $replayedChunks++;
    }
    $memoryGrowth = memory_get_usage(true) - $memoryBeforeReplay;

    expect($replayedChunks)->toBe(100_000)
        ->and(hash_final($recordHash))->toBe(hash('sha256', str_repeat('x', 100_000)))
        ->and(hash_final($replayHash))->toBe(hash('sha256', str_repeat('x', 100_000)))
        ->and($memoryGrowth)->toBeLessThan(8 * 1024 * 1024);
});

test('empty, newline, NUL, and invalid UTF-8 chunks round-trip as frames', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest(streamed: true);
    $chunks = ['', "line1\r\nline2\n", "\x00\xFF\x80"];
    $recorded = $store->record(
        $request,
        HttpResponse::streaming(200, [], new IterableStream($chunks)),
    );
    iterator_to_array($recorded->stream());

    $replay = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    expect(iterator_to_array($replay->replay($request)->stream()))->toBe($chunks);
});

test('replay validates malformed frames and stream completion semantics', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest(streamed: true);
    $recorded = $store->record(
        $request,
        HttpResponse::streaming(200, [], new IterableStream(['valid'])),
    );
    iterator_to_array($recorded->stream());
    $frame = $this->directory . '/interactions/000001/response.chunks.ndjson';
    file_put_contents($frame, "not-base64\n");

    $replay = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    expect(fn () => $replay->replay($request))->toThrow(CassetteCorruptException::class);

    $cleanStore = FilesystemCassetteStore::fromDirectory(
        $this->directory . '/clean',
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $cleanRecorded = $cleanStore->record(
        $request,
        HttpResponse::streaming(200, [], new IterableStream(['valid'])),
    );
    $stream = $cleanRecorded->stream();
    expect($stream->current())->toBe('valid');
    $stream->next();
    expect($stream->valid())->toBeFalse();
    $cleanReplay = FilesystemCassetteStore::fromDirectory(
        $this->directory . '/clean',
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $response = $cleanReplay->replay($request);
    iterator_to_array($response->stream());
    expect($response->rawStream()->isCompleted())->toBeTrue()
        ->and(fn () => iterator_to_array($response->stream()))
        ->toThrow(LogicException::class);
});

test('upstream stream failures do not publish a finalized cassette interaction', function () {
    $source = (function (): Generator {
        yield 'first';
        throw new RuntimeException('upstream failed');
    })();
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $response = $store->record(
        cassetteRequest(streamed: true),
        HttpResponse::streaming(200, [], new IterableStream($source)),
    );

    expect(fn () => iterator_to_array($response->stream()))
        ->toThrow(RuntimeException::class, 'upstream failed');
    expect(glob($this->directory . '/interactions/[0-9]*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.reserved-*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.tmp-*'))->toBe([]);
});

test('filesystem store rejects legacy single-file fixtures explicitly', function () {
    mkdir($this->directory, 0700, true);
    file_put_contents($this->directory . '/legacy.json', '{}');

    expect(fn () => FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    ))->toThrow(LegacyCassetteException::class);
});

test('filesystem store reports unsupported and corrupt fixtures as typed failures', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new DefaultRequestRedactor(),
        (new RecordReplayPolicy())->matcher,
    );
    $request = cassetteRequest();
    $store->record($request, HttpResponse::sync(200, [], 'body'));
    $interaction = $this->directory . '/interactions/000001/interaction.json';
    $data = json_decode((string) file_get_contents($interaction), true);
    $data['version'] = 99;
    file_put_contents($interaction, json_encode($data));

    expect(fn () => $store->replay($request))->toThrow(UnsupportedCassetteVersionException::class);

    $data['version'] = 1;
    file_put_contents($interaction, json_encode($data));
    unlink($this->directory . '/interactions/000001/response.body');

    expect(fn () => $store->replay($request))->toThrow(CassetteCorruptException::class);
});

test('failed cassette writes leave no published or temporary interaction', function () {
    $store = FilesystemCassetteStore::fromDirectory(
        $this->directory,
        new class implements \Cognesy\Http\Extras\Support\RecordReplay\FixtureSanitizer {
            public function redact(array $requestData): array { return $requestData; }

            public function redactResponse(array $responseData): array {
                $responseData['body'] = ['not', 'binary'];
                return $responseData;
            }

            public function redactStream(iterable $chunks): iterable { yield from $chunks; }
        },
        (new RecordReplayPolicy())->matcher,
    );

    expect(fn () => $store->record(cassetteRequest(), HttpResponse::sync(200, [], 'body')))
        ->toThrow(CassetteSerializationException::class);

    expect(glob($this->directory . '/interactions/[0-9]*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.tmp-*'))->toBe([])
        ->and(glob($this->directory . '/interactions/.reserved-*'))->toBe([]);
});
