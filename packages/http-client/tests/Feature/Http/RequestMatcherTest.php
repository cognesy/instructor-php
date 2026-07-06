<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\ExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\RequestRecords;

beforeEach(function () {
    $this->dir = sys_get_temp_dir() . '/http_matcher_test_' . uniqid();
});

afterEach(function () {
    if (is_dir($this->dir)) {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }
});

// --- ExactHashMatcher: the extracted baseline behavior ---

test('ExactHashMatcher fingerprints by method, url and body only', function () {
    $matcher = new ExactHashMatcher();

    $a = new HttpRequest('https://api.example.com/x', 'POST',
        ['Authorization' => 'Bearer a'], '{"id":1}', ['timeout' => 5]);
    $b = new HttpRequest('https://api.example.com/x', 'POST',
        ['Authorization' => 'Bearer b'], '{"id":1}', ['timeout' => 30]);
    $different = new HttpRequest('https://api.example.com/x', 'POST',
        [], '{"id":2}', []);

    // headers + options ignored -> same fingerprint
    expect($matcher->fingerprint($a))->toBe($matcher->fingerprint($b));
    // body differs -> different fingerprint
    expect($matcher->fingerprint($a))->not->toBe($matcher->fingerprint($different));
});

// --- Contract: the matching strategy is swappable with NO caller change ---

test('RequestRecords honors an injected matcher without changing save/find callers', function () {
    // A stub matcher that puts every request into ONE equivalence class.
    // If lookup is driven by the matcher (not baked into storage), then a record
    // saved for one request must be found for a completely different request.
    $collapsing = new class implements RequestMatcher {
        public function fingerprint(HttpRequest $request): string {
            return 'same-for-everything';
        }
    };

    $records = new RequestRecords($this->dir, $collapsing);

    $saved = new HttpRequest('https://api.example.com/a', 'GET', [], '', []);
    $records->save($saved, MockHttpResponseFactory::success(body: '{"hit":true}'));

    // Different method, url AND body — would never match under ExactHashMatcher.
    $lookup = new HttpRequest('https://other.example.com/z', 'POST', [], '{"q":9}', []);
    $record = $records->find($lookup); // same call site as always

    expect($record)->not->toBeNull()
        ->and($record?->getResponseBody())->toBe('{"hit":true}');
});

test('ExactHashMatcher ignores credential query-param values (keyless replay)', function () {
    // A recording made with a real ?key= must match a keyless replay (dummy key).
    $matcher = new ExactHashMatcher();
    $withRealKey = new HttpRequest('https://gen.example/x:embed?key=AIzaREALKEY123456', 'POST', [], '{}', []);
    $withDummy = new HttpRequest('https://gen.example/x:embed?key=dummy-replay-key', 'POST', [], '{}', []);
    $differentPath = new HttpRequest('https://gen.example/y:embed?key=AIzaREALKEY123456', 'POST', [], '{}', []);

    expect($matcher->fingerprint($withRealKey))->toBe($matcher->fingerprint($withDummy));
    expect($matcher->fingerprint($withRealKey))->not->toBe($matcher->fingerprint($differentPath));
});

test('default matcher (no arg) preserves exact-match semantics', function () {
    $records = new RequestRecords($this->dir); // no matcher -> ExactHashMatcher

    $saved = new HttpRequest('https://api.example.com/a', 'GET', [], '', []);
    $records->save($saved, MockHttpResponseFactory::success(body: '{"ok":1}'));

    // Same method|url|body but different headers/options -> still matches.
    $sameShape = new HttpRequest('https://api.example.com/a', 'GET',
        ['X-Trace' => 'z'], '', ['timeout' => 99]);
    expect($records->find($sameShape))->not->toBeNull();

    // Different body -> no match.
    $different = new HttpRequest('https://api.example.com/a', 'POST', [], '{"x":1}', []);
    expect($records->find($different))->toBeNull();
});
