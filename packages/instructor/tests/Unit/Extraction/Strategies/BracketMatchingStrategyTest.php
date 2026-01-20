<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\BracketMatchingExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('BracketMatchingExtractor', function () {
    beforeEach(function () {
        $this->extractor = new BracketMatchingExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('bracket_matching');
    });

    it('extracts JSON from text with prefix', function () {
        $content = 'Here is the response: {"name":"John","age":30}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('extracts JSON from text with suffix', function () {
        $content = '{"name":"John"} - that is the data';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('extracts JSON from text with prefix and suffix', function () {
        $content = 'Response: {"name":"John"} end.';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('extracts nested JSON', function () {
        $content = 'Data: {"user":{"name":"John"}}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['user' => ['name' => 'John']]);
    });

    it('handles standalone JSON', function () {
        $content = '{"name":"John"}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('fails when no opening brace', function () {
        $input = ExtractionInput::fromContent('No JSON here', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'No opening brace found');
    });

    it('fails when no closing brace', function () {
        $input = ExtractionInput::fromContent('Incomplete: {"name":"John"', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'No closing brace found');
    });

    it('fails when braces are in wrong order', function () {
        $input = ExtractionInput::fromContent('} before {', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Invalid brace positions');
    });

    it('fails on invalid JSON between braces', function () {
        $input = ExtractionInput::fromContent('Text {invalid json} more', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Invalid JSON between braces');
    });

    it('handles JSON with internal braces in strings', function () {
        // Note: Simple bracket matching might include extra content
        // This test documents current behavior
        $content = '{"text":"has { and } inside"}';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['text' => 'has { and } inside']);
    });
});
