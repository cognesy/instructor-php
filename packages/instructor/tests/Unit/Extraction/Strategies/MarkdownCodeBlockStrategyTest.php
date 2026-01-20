<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\MarkdownBlockExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('MarkdownBlockExtractor', function () {
    beforeEach(function () {
        $this->extractor = new MarkdownBlockExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('markdown_code_block');
    });

    it('extracts JSON from ```json block', function () {
        $content = <<<'MD'
        Here is the response:
        ```json
        {"name":"John","age":30}
        ```
        MD;

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('extracts JSON from ```JSON block (uppercase)', function () {
        $content = <<<'MD'
        ```JSON
        {"name":"John"}
        ```
        MD;

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('extracts JSON from plain ``` block', function () {
        $content = <<<'MD'
        ```
        {"name":"John"}
        ```
        MD;

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });

    it('handles newlines in JSON', function () {
        $content = <<<'MD'
        ```json
        {
            "name": "John",
            "age": 30
        }
        ```
        MD;

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('fails when no code block present', function () {
        $input = ExtractionInput::fromContent('{"name":"John"}', OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'No markdown code block found');
    });

    it('fails on empty code block', function () {
        $content = <<<'MD'
        ```json
        ```
        MD;

        $input = ExtractionInput::fromContent($content, OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Empty code block');
    });

    it('fails on invalid JSON in code block', function () {
        $content = <<<'MD'
        ```json
        {invalid json}
        ```
        MD;

        $input = ExtractionInput::fromContent($content, OutputMode::Json);
        expect(fn() => $this->extractor->extract($input))
            ->toThrow(ExtractionException::class, 'Invalid JSON in code block');
    });

    it('extracts first code block when multiple present', function () {
        $content = <<<'MD'
        ```json
        {"first":"one"}
        ```
        Some text
        ```json
        {"second":"two"}
        ```
        MD;

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['first' => 'one']);
    });

    it('handles code block with no space after json', function () {
        $content = '```json{"name":"John"}```';

        $result = $this->extractor->extract(ExtractionInput::fromContent($content, OutputMode::Json));

        expect($result)->toBe(['name' => 'John']);
    });
});
