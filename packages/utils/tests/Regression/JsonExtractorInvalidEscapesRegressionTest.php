<?php declare(strict_types=1);

use Cognesy\Utils\Json\JsonExtractor;
use Cognesy\Utils\Json\JsonDecoder;

/**
 * Regression: LLMs produce JSON with invalid escape sequences like \R (from
 * \RuntimeException) and \T (from \Throwable). json_decode() rejects these,
 * causing JsonExtractor::first() to return null even when the JSON structure
 * is otherwise valid. The repair heuristic now doubles the backslash (\R → \\R)
 * so json_decode interprets it as a literal backslash + letter.
 */

it('extracts JSON containing invalid escape sequences from LLM class names', function () {
    $json = '{"class": "\RuntimeException", "type": "\Throwable"}';
    $result = JsonExtractor::first($json);
    expect($result)->not->toBeNull()
        ->and($result['class'])->toBe('\RuntimeException')
        ->and($result['type'])->toBe('\Throwable');
});

it('extracts JSON with invalid escapes from markdown code blocks', function () {
    $text = "Here is the result:\n```json\n{\"error\": \"\\RuntimeException thrown in \\TestCase\"}\n```\nDone.";
    $result = JsonExtractor::first($text);
    expect($result)->not->toBeNull()
        ->and($result['error'])->toBe('\RuntimeException thrown in \TestCase');
});

it('preserves valid escape sequences when repairing invalid ones', function () {
    $json = '{"msg": "line1\nline2\ttab", "class": "\RuntimeException"}';
    $result = JsonExtractor::first($json);
    expect($result)->not->toBeNull()
        ->and($result['msg'])->toContain("\n")
        ->and($result['msg'])->toContain("\t")
        ->and($result['class'])->toBe('\RuntimeException');
});

it('handles the full decode pipeline with invalid escapes in surrounding text', function () {
    $text = "The API returned:\n```json\n{\"exception\": \"\\RuntimeException\", \"code\": 500}\n```";
    $result = JsonDecoder::decode($text);
    expect($result)->toBeArray()
        ->and($result['exception'])->toBe('\RuntimeException')
        ->and($result['code'])->toBe(500);
});

it('does not regress: extracting first JSON from text with multiple objects', function () {
    $text = 'Text {"a":1} more {"b":2}';
    $result = JsonExtractor::first($text);
    expect($result)->toBe(['a' => 1]);
});

it('tryStrictDecode returns null for non-JSON text', function () {
    expect(JsonDecoder::tryStrictDecode('not json at all'))->toBeNull();
    expect(JsonDecoder::tryStrictDecode('Hello world'))->toBeNull();
    expect(JsonDecoder::tryStrictDecode(''))->toBeNull();
});
