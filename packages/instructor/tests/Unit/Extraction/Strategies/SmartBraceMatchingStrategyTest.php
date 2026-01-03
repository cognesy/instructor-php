<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Extractors\SmartBraceExtractor;

describe('SmartBraceExtractor', function () {
    beforeEach(function () {
        $this->extractor = new SmartBraceExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('smart_brace_matching');
    });

    it('extracts simple JSON', function () {
        $content = '{"name":"John"}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('extracts JSON from surrounding text', function () {
        $content = 'Here is the response: {"name":"John"} and some more text';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('handles braces inside strings correctly', function () {
        $content = '{"text":"This has { and } inside it"}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"text":"This has { and } inside it"}');
    });

    it('handles escaped quotes in strings', function () {
        $content = '{"text":"He said \"hello\""}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"text":"He said \"hello\""}');
    });

    it('handles nested objects', function () {
        $content = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"user":{"name":"John","address":{"city":"NYC"}}}');
    });

    it('handles complex escaping', function () {
        $content = '{"path":"C:\\\\Users\\\\John","text":"Say \\"hi\\""}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded['path'])->toBe('C:\\Users\\John');
        expect($decoded['text'])->toBe('Say "hi"');
    });

    it('finds valid JSON after invalid attempt', function () {
        // First { is part of invalid structure, second is valid JSON
        $content = 'Text { not json } then {"valid":"json"}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"valid":"json"}');
    });

    it('fails when no valid JSON found', function () {
        $result = $this->extractor->extract('No JSON here at all');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('No valid JSON found with smart brace matching');
    });

    it('fails when only invalid JSON present', function () {
        $result = $this->extractor->extract('{invalid: not json}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('No valid JSON found with smart brace matching');
    });

    it('handles unclosed strings gracefully', function () {
        // String never closes - strategy should handle this
        $result = $this->extractor->extract('{"text":"unclosed string');

        expect($result->isFailure())->toBeTrue();
    });

    it('handles deeply nested JSON', function () {
        $content = '{"a":{"b":{"c":{"d":{"e":"deep"}}}}}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });
});