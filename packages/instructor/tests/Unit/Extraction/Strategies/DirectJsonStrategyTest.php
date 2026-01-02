<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;

describe('DirectJsonStrategy', function () {
    beforeEach(function () {
        $this->strategy = new DirectJsonStrategy();
    });

    it('has correct name', function () {
        expect($this->strategy->name())->toBe('direct');
    });

    it('extracts valid JSON object', function () {
        $content = '{"name":"John","age":30}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });

    it('extracts valid JSON array', function () {
        $content = '[1, 2, 3]';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });

    it('handles whitespace around JSON', function () {
        $content = "  \n  {\"name\":\"John\"}  \n  ";

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('fails on empty content', function () {
        $result = $this->strategy->extract('');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty content');
    });

    it('fails on whitespace-only content', function () {
        $result = $this->strategy->extract("   \n\t  ");

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Empty content');
    });

    it('fails on invalid JSON', function () {
        $result = $this->strategy->extract('{invalid}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('fails on plain text', function () {
        $result = $this->strategy->extract('This is just plain text');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('fails on JSON with surrounding text', function () {
        $result = $this->strategy->extract('Here is some JSON: {"name":"John"}');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Not valid JSON');
    });

    it('extracts nested JSON', function () {
        $content = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe($content);
    });
});
