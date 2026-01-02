<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Strategies\BracketMatchingStrategy;

describe('BracketMatchingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new BracketMatchingStrategy();
    });

    it('has correct name', function () {
        expect($this->strategy->name())->toBe('bracket_matching');
    });

    it('extracts JSON from text with prefix', function () {
        $content = 'Here is the response: {"name":"John","age":30}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John","age":30}');
    });

    it('extracts JSON from text with suffix', function () {
        $content = '{"name":"John"} - that is the data';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('extracts JSON from text with prefix and suffix', function () {
        $content = 'Response: {"name":"John"} end.';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('extracts nested JSON', function () {
        $content = 'Data: {"user":{"name":"John"}}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"user":{"name":"John"}}');
    });

    it('handles standalone JSON', function () {
        $content = '{"name":"John"}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"name":"John"}');
    });

    it('fails when no opening brace', function () {
        $result = $this->strategy->extract('No JSON here');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('No opening brace found');
    });

    it('fails when no closing brace', function () {
        $result = $this->strategy->extract('Incomplete: {"name":"John"');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('No closing brace found');
    });

    it('fails when braces are in wrong order', function () {
        $result = $this->strategy->extract('} before {');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toBe('Invalid brace positions');
    });

    it('fails on invalid JSON between braces', function () {
        $result = $this->strategy->extract('Text {invalid json} more');

        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('Invalid JSON between braces');
    });

    it('handles JSON with internal braces in strings', function () {
        // Note: Simple bracket matching might include extra content
        // This test documents current behavior
        $content = '{"text":"has { and } inside"}';

        $result = $this->strategy->extract($content);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe('{"text":"has { and } inside"}');
    });
});
