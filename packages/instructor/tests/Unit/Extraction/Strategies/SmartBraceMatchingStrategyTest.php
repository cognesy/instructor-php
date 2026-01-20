<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\SmartBraceExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('SmartBraceExtractor', function () {
    beforeEach(function () {
        $this->extractor = new SmartBraceExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('smart_brace_matching');
    });

    it('extracts simple JSON', function () {
        $content = '{"name":"John"}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('extracts JSON from surrounding text', function () {
        $content = 'Here is the response: {"name":"John"} and some more text';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('handles braces inside strings correctly', function () {
        $content = '{"text":"This has { and } inside it"}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['text' => 'This has { and } inside it']);
    });

    it('handles escaped quotes in strings', function () {
        $content = '{"text":"He said \"hello\""}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['text' => 'He said "hello"']);
    });

    it('handles nested objects', function () {
        $content = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe([
            'user' => [
                'name' => 'John',
                'address' => ['city' => 'NYC'],
            ],
        ]);
    });

    it('handles complex escaping', function () {
        $content = '{"path":"C:\\\\Users\\\\John","text":"Say \\"hi\\""}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result['path'])->toBe('C:\\Users\\John');
        expect($result['text'])->toBe('Say "hi"');
    });

    it('finds valid JSON after invalid attempt', function () {
        // First { is part of invalid structure, second is valid JSON
        $content = 'Text { not json } then {"valid":"json"}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['valid' => 'json']);
    });

    it('fails when no valid JSON found', function () {
        $input = ExtractionInput::fromContent('No JSON here at all', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'No valid JSON found with smart brace matching');
    });

    it('fails when only invalid JSON present', function () {
        $input = ExtractionInput::fromContent('{invalid: not json}', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'No valid JSON found with smart brace matching');
    });

    it('handles unclosed strings gracefully', function () {
        // String never closes - strategy should handle this
        $input = ExtractionInput::fromContent('{"text":"unclosed string', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class);
    });

    it('handles deeply nested JSON', function () {
        $content = '{"a":{"b":{"c":{"d":{"e":"deep"}}}}}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]]);
    });
});
