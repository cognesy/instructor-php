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
