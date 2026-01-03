<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;

describe('DirectJsonExtractor', function () {
    beforeEach(function () {
        $this->extractor = new DirectJsonExtractor();
    });

    it('has correct name', function () {
        expect($this->extractor->name())->toBe('direct');
    });

    it('extracts valid JSON object', function () {
        $content = '{"name":"John","age":30}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });

    it('extracts valid JSON array', function () {
        $content = '[1, 2, 3]';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });

    it('handles whitespace around JSON', function () {
        $content = "  \n  {\"name\":\"John\"}  \n  ";

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('fails on empty content', function () {
        $result = $this->extractor->extract('');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty content');
    });

    it('fails on whitespace-only content', function () {
        $result = $this->extractor->extract("   \n\t  ");

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty content');
    });

    it('fails on invalid JSON', function () {
        $result = $this->extractor->extract('{invalid}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('fails on plain text', function () {
        $result = $this->extractor->extract('This is just plain text');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('fails on JSON with surrounding text', function () {
        $result = $this->extractor->extract('Here is some JSON: {"name":"John"}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('extracts nested JSON', function () {
        $content = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        $result = $this->extractor->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });
});