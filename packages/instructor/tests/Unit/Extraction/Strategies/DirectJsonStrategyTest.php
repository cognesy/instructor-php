<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('DirectJsonExtractor', function () {
    beforeEach(function () {
        $this->extractor = new DirectJsonExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('direct');
    });

    it('extracts valid JSON object', function () {
        $content = '{"name":"John","age":30}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('extracts valid JSON array', function () {
        $content = '[1, 2, 3]';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe([1, 2, 3]);
    });

    it('handles whitespace around JSON', function () {
        $content = "  \n  {\"name\":\"John\"}  \n  ";

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('fails on empty content', function () {
        $input = ExtractionInput::fromContent('', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Empty content');
    });

    it('fails on whitespace-only content', function () {
        $input = ExtractionInput::fromContent("   \n\t  ", OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Empty content');
    });

    it('fails on invalid JSON', function () {
        $input = ExtractionInput::fromContent('{invalid}', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Not valid JSON');
    });

    it('fails on plain text', function () {
        $input = ExtractionInput::fromContent('This is just plain text', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Not valid JSON');
    });

    it('fails on JSON with surrounding text', function () {
        $input = ExtractionInput::fromContent('Here is some JSON: {"name":"John"}', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Not valid JSON');
    });

    it('extracts nested JSON', function () {
        $content = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe([
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'NYC',
                ],
            ],
        ]);
    });
});
