<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\RequestRecords;

beforeEach(function () {
    $this->dir = sys_get_temp_dir() . '/http_redaction_test_' . uniqid();
});

afterEach(function () {
    if (is_dir($this->dir)) {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }
});

// A realistic sample of provider auth headers with realistically-shaped values.
function redactionSampleHeaders(): array {
    return [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer sk-proj-abcdef0123456789abcdef0123456789',
        'x-api-key' => 'sk-ant-api03-DEADBEEFdeadbeefDEADBEEFdeadbeef',
        'x-goog-api-key' => 'AIzaSyD-EXAMPLE_KEY_1234567890abcdef1234',
        'api-key' => 'azure-1234567890abcdef',
        'Cookie' => 'session=supersecretcookievalue',
    ];
}

test('DefaultRequestRedactor masks every sensitive header, case-insensitively', function () {
    $redactor = new DefaultRequestRedactor();

    $out = $redactor->redact([
        'url' => 'https://api.example.com/x',
        'method' => 'POST',
        'headers' => redactionSampleHeaders(),
        'body' => '{"prompt":"hi"}',
        'options' => [],
    ]);

    expect($out['headers']['Authorization'])->toBe(DefaultRequestRedactor::MASK)
        ->and($out['headers']['x-api-key'])->toBe(DefaultRequestRedactor::MASK)
        ->and($out['headers']['x-goog-api-key'])->toBe(DefaultRequestRedactor::MASK)
        ->and($out['headers']['api-key'])->toBe(DefaultRequestRedactor::MASK)
        ->and($out['headers']['Cookie'])->toBe(DefaultRequestRedactor::MASK)
        // non-sensitive headers untouched
        ->and($out['headers']['Content-Type'])->toBe('application/json')
        // body preserved (participates in matching)
        ->and($out['body'])->toBe('{"prompt":"hi"}');
});

test('redaction is applied on save so secrets never reach disk (guard scan)', function () {
    // Default RequestRecords wires DefaultRequestRedactor.
    $records = new RequestRecords($this->dir);

    $request = new HttpRequest(
        'https://generativelanguage.googleapis.com/v1beta/models/x:streamGenerateContent',
        'POST',
        redactionSampleHeaders(),
        '{"contents":[]}',
        [],
    );
    $records->save($request, MockHttpResponseFactory::success(body: '{"ok":true}'));

    // Guard: scan the recordings tree for key-shaped strings — the exact check R6/CI runs.
    $keyShapes = [
        '/AIza[0-9A-Za-z_\-]{10,}/',   // Google
        '/sk-ant-[0-9A-Za-z_\-]{10,}/', // Anthropic
        '/sk-proj-[0-9A-Za-z_\-]{10,}/', // OpenAI project
        '/Bearer\s+[0-9A-Za-z._\-]{12,}/', // bearer tokens
        '/supersecretcookievalue/',    // the cookie value
    ];

    foreach (glob($this->dir . '/*.json') ?: [] as $file) {
        $contents = file_get_contents($file) ?: '';
        foreach ($keyShapes as $pattern) {
            expect(preg_match($pattern, $contents))->toBe(0, "Secret leaked into {$file} matching {$pattern}");
        }
        // and the mask IS present, proving redaction ran (not just absent headers)
        expect($contents)->toContain(DefaultRequestRedactor::MASK);
    }
});

test('API key carried as a URL query param is masked (Gemini ?key=)', function () {
    // Regression pin: Gemini embeddings put the key in the URL, not a header.
    $records = new RequestRecords($this->dir);
    $request = new HttpRequest(
        'https://generativelanguage.googleapis.com/v1beta/models/x:batchEmbedContents?key=AIzaSyD-EXAMPLE_KEY_urlparam1234567890ab',
        'POST', [], '{}', [],
    );
    $file = $records->save($request, MockHttpResponseFactory::success(body: 'ok'));

    expect(file_get_contents($file))
        ->not->toContain('AIzaSyD-EXAMPLE_KEY_urlparam1234567890ab')
        ->toContain('key=');
});

test('the exact R0 leak (x-goog-api-key AIza...) is masked on save', function () {
    // Regression pin for the R0 spike finding.
    $records = new RequestRecords($this->dir);
    $request = new HttpRequest('https://gen.example/x:streamGenerateContent', 'POST',
        ['x-goog-api-key' => 'AIzaSyD-EXAMPLE_KEY_1234567890abcdefXYZ'], '{}', []);
    $file = $records->save($request, MockHttpResponseFactory::success(body: 'ok'));

    expect(file_get_contents($file))
        ->not->toContain('AIzaSyD-EXAMPLE_KEY_1234567890abcdefXYZ')
        ->toContain(DefaultRequestRedactor::MASK);
});

test('response headers, body fields, and streamed chunks are redacted before persistence', function () {
    $records = new RequestRecords($this->dir);
    $response = MockHttpResponseFactory::success(
        headers: [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'session=response-cookie-secret',
            'Authorization' => 'Bearer response-header-secret',
        ],
        body: '{"access_token":"response-body-secret","ok":true}',
    );
    $request = new HttpRequest('https://api.example.com/token', 'GET', [], '', []);
    $file = $records->save($request, $response);

    $contents = file_get_contents($file) ?: '';
    expect($contents)->not->toContain('response-cookie-secret')
        ->and($contents)->not->toContain('response-header-secret')
        ->and($contents)->not->toContain('response-body-secret')
        ->and($contents)->toContain(DefaultRequestRedactor::MASK);

    $streamedRequest = new HttpRequest('https://api.example.com/stream', 'GET', [], '', ['stream' => true]);
    $streamed = MockHttpResponseFactory::streaming(
        headers: ['Set-Cookie' => 'stream-cookie-secret'],
        chunks: ['{"token":"stream-', 'body-secret"}'],
    );
    $streamedFile = $records->save($streamedRequest, $streamed);
    $streamedContents = file_get_contents($streamedFile) ?: '';

    expect($streamedContents)->not->toContain('stream-cookie-secret')
        ->and($streamedContents)->not->toContain('body-secret')
        ->and($streamedContents)->toContain(DefaultRequestRedactor::MASK);
});

test('stream redaction masks secrets split across chunks without truncating the body', function () {
    $redactor = new DefaultRequestRedactor();
    $body = '{"token":"split-secret","safe":true}';

    $redactedChunks = iterator_to_array($redactor->redactStream(str_split($body, 1)), false);

    expect(implode('', $redactedChunks))
        ->toBe('{"token":"' . DefaultRequestRedactor::MASK . '","safe":true}')
        ->not->toContain('split-secret');

    $authorization = iterator_to_array(
        $redactor->redactStream(str_split("Authorization: Bearer split-token\n", 1)),
        false,
    );

    expect(implode('', $authorization))
        ->toBe('Authorization: ' . DefaultRequestRedactor::MASK . "\n")
        ->not->toContain('split-token');
});

test('stream redaction keeps memory bounded while processing 100K chunks', function () {
    $redactor = new DefaultRequestRedactor();
    $hash = hash_init('sha256');
    $chunkCount = 0;
    $memoryBefore = memory_get_usage(true);

    $chunks = static function (): Generator {
        for ($index = 0; $index < 100_000; $index++) {
            yield 'x';
        }
    };

    foreach ($redactor->redactStream($chunks()) as $chunk) {
        hash_update($hash, $chunk);
        $chunkCount++;
    }

    $memoryGrowth = memory_get_usage(true) - $memoryBefore;

    expect($chunkCount)->toBe(100_000)
        ->and(hash_final($hash))->toBe(hash('sha256', str_repeat('x', 100_000)))
        ->and($memoryGrowth)->toBeLessThan(8 * 1024 * 1024);
});

test('streamed persistence sanitizes the complete logical body', function () {
    $records = new RequestRecords($this->dir);
    $request = new HttpRequest('https://api.example.com/stream', 'POST', [], '', ['stream' => true]);
    $response = MockHttpResponseFactory::streaming(headers: [], chunks: []);
    $body = '{"token":"stream-secret","message":"complete"}';

    $file = $records->saveStreamed($request, $response, str_split($body, 1));
    $data = json_decode((string) file_get_contents($file), true);
    $capturedBody = implode('', $data['chunks'] ?? []);

    expect($capturedBody)
        ->toBe('{"token":"' . DefaultRequestRedactor::MASK . '","message":"complete"}')
        ->not->toContain('stream-secret');
});

test('recording directories are created owner-only on POSIX systems', function () {
    if (DIRECTORY_SEPARATOR === '\\') {
        return;
    }

    $records = new RequestRecords($this->dir);
    expect(fileperms($records->getStorageDir()) & 0777)->toBe(0700);
});
