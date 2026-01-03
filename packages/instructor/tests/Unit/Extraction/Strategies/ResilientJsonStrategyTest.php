<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;

describe('ResilientJsonExtractor', function () {
    beforeEach(function () {
        $this->extractor = new ResilientJsonExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('resilient');
    });

    it('extracts valid JSON object', function () {
        $content = '{"name":"John","age":30}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('handles trailing commas', function () {
        $content = '{"name":"John","age":30,}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('handles nested objects with trailing commas', function () {
        $content = '{"user":{"name":"John",},"active":true,}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded['user']['name'])->toBe('John');
        expect($decoded['active'])->toBe(true);
    });

    it('handles arrays with trailing commas', function () {
        $content = '{"items":[1,2,3,]}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded['items'])->toBe([1, 2, 3]);
    });

    it('fails on empty content', function () {
        $result = $this->extractor->extract('');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty content');
    });

    it('fails on plain text (produces scalar)', function () {
        $result = $this->extractor->extract('This is just plain text');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('scalar');
    });

    it('fails on markdown wrapped JSON (produces scalar)', function () {
        $content = '```json{"name":"John"}```';

        $result = $this->extractor->extract($content);

        // ResilientJson parses the backtick as a scalar, not the JSON
        expect($result->isFailure())->toBeTrue();
    });

    it('handles valid JSON array', function () {
        $content = '[1,2,3]';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        $decoded = json_decode($result->unwrap(), true);
        expect($decoded)->toBe([1, 2, 3]);
    });
});