<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('ResilientJsonExtractor', function () {
    beforeEach(function () {
        $this->extractor = new ResilientJsonExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('resilient');
    });

    it('extracts valid JSON object', function () {
        $content = '{"name":"John","age":30}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('handles trailing commas', function () {
        $content = '{"name":"John","age":30,}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('handles nested objects with trailing commas', function () {
        $content = '{"user":{"name":"John",},"active":true,}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result['user']['name'])->toBe('John');
        expect($result['active'])->toBe(true);
    });

    it('handles arrays with trailing commas', function () {
        $content = '{"items":[1,2,3,]}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result['items'])->toBe([1, 2, 3]);
    });

    it('fails on empty content', function () {
        $input = ExtractionInput::fromContent('', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Empty content');
    });

    it('fails on plain text (produces scalar)', function () {
        $input = ExtractionInput::fromContent('This is just plain text', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'scalar');
    });

    it('fails on markdown wrapped JSON (produces scalar)', function () {
        $content = '```json{"name":"John"}```';

        $input = ExtractionInput::fromContent($content, OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'scalar');
    });

    it('handles valid JSON array', function () {
        $content = '[1,2,3]';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe([1, 2, 3]);
    });
});
