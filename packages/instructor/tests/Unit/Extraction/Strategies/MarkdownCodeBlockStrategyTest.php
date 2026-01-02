<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Strategies\MarkdownCodeBlockStrategy;

describe('MarkdownCodeBlockStrategy', function () {
    beforeEach(function () {
        $this->strategy = new MarkdownCodeBlockStrategy();
    });

    it('has correct name', function () {
        expect($this->strategy->name())->toBe('markdown_code_block');
    });

    it('extracts JSON from ```json block', function () {
        $content = <<<'MD'
        Here is the response:
        ```json
        {"name":"John","age":30}
        ```
        MD;

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John","age":30}');
    });

    it('extracts JSON from ```JSON block (uppercase)', function () {
        $content = <<<'MD'
        ```JSON
        {"name":"John"}
        ```
        MD;

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('extracts JSON from plain ``` block', function () {
        $content = <<<'MD'
        ```
        {"name":"John"}
        ```
        MD;

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
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

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('fails when no code block present', function () {
        $result = $this->strategy->extract('{"name":"John"}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('No markdown code block found');
    });

    it('fails on empty code block', function () {
        $content = <<<'MD'
        ```json
        ```
        MD;

        $result = $this->strategy->extract($content);

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty code block');
    });

    it('fails on invalid JSON in code block', function () {
        $content = <<<'MD'
        ```json
        {invalid json}
        ```
        MD;

        $result = $this->strategy->extract($content);

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Invalid JSON in code block');
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

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"first":"one"}');
    });

    it('handles code block with no space after json', function () {
        $content = '```json{"name":"John"}```';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });
});
