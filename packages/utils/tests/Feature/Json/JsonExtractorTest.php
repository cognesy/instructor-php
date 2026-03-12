<?php declare(strict_types=1);

use Cognesy\Utils\Json\JsonExtractor;

describe('JsonExtractor', function () {
    // -- first() --

    it('extracts raw JSON object', function () {
        expect(JsonExtractor::first('{"a":1}'))->toBe(['a' => 1]);
    });

    it('extracts raw JSON array', function () {
        expect(JsonExtractor::first('[1,2,3]'))->toBe([1, 2, 3]);
    });

    it('extracts JSON from surrounding text', function () {
        expect(JsonExtractor::first('Here is JSON: {"a":1} done'))
            ->toBe(['a' => 1]);
    });

    it('extracts JSON from text before and after', function () {
        expect(JsonExtractor::first('Before {"name":"John","age":30} After'))
            ->toBe(['name' => 'John', 'age' => 30]);
    });

    it('extracts JSON from markdown code block', function () {
        $md = "# Title\n```json\n{\"key\":\"value\"}\n```\nOther text";
        expect(JsonExtractor::first($md))->toBe(['key' => 'value']);
    });

    it('extracts JSON from plain code block', function () {
        $md = "Here:\n```\n{\"a\":1}\n```\n";
        expect(JsonExtractor::first($md))->toBe(['a' => 1]);
    });

    it('returns null when no JSON found', function () {
        expect(JsonExtractor::first('no json here'))->toBeNull();
    });

    it('returns null for empty input', function () {
        expect(JsonExtractor::first(''))->toBeNull();
        expect(JsonExtractor::first('   '))->toBeNull();
    });

    it('extracts first valid JSON when multiple present', function () {
        $text = 'Text {"a":1} more {"b":2}';
        expect(JsonExtractor::first($text))->toBe(['a' => 1]);
    });

    it('handles nested braces in strings', function () {
        $text = 'text {"msg":"has { and } chars","a":1} end';
        expect(JsonExtractor::first($text))->toBe(['msg' => 'has { and } chars', 'a' => 1]);
    });

    it('handles nested objects', function () {
        $text = 'before {"outer":{"inner":"value"}} after';
        expect(JsonExtractor::first($text))
            ->toBe(['outer' => ['inner' => 'value']]);
    });

    it('skips invalid JSON and finds valid one', function () {
        $text = '{"broken {valid_key":"val"} text {"good":"json"}';
        $result = JsonExtractor::first($text);
        expect($result)->toBeArray();
        // Should find a valid JSON object
        expect($result)->not->toBeNull();
    });

    // -- all() --

    it('extracts all JSON objects from text', function () {
        $text = '{"a":1} text {"b":2} more {"c":3}';
        $results = JsonExtractor::all($text);
        expect($results)->toHaveCount(3);
        expect($results[0])->toBe(['a' => 1]);
        expect($results[1])->toBe(['b' => 2]);
        expect($results[2])->toBe(['c' => 3]);
    });

    it('returns empty array when no JSON found', function () {
        expect(JsonExtractor::all('no json'))->toBe([]);
    });

    it('returns empty array for empty input', function () {
        expect(JsonExtractor::all(''))->toBe([]);
    });

    it('handles mixed arrays and objects', function () {
        $text = '{"a":1} then [1,2,3]';
        $results = JsonExtractor::all($text);
        expect($results)->toHaveCount(2);
        expect($results[0])->toBe(['a' => 1]);
        expect($results[1])->toBe([1, 2, 3]);
    });
});
