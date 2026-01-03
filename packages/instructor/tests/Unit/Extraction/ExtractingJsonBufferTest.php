<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Extraction\Buffers\ExtractingJsonBuffer;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;

describe('ExtractingJsonBuffer', function () {
    it('creates empty buffer with default extractors', function () {
        $buffer = ExtractingJsonBuffer::empty();

        expect($buffer->raw())->toBe('');
        expect($buffer->normalized())->toBe('');
        expect($buffer->isEmpty())->toBeTrue();
        expect($buffer->extractors())->toHaveCount(2);
    });

    it('creates buffer with custom extractors', function () {
        $buffer = ExtractingJsonBuffer::withExtractors(new DirectJsonExtractor());

        expect($buffer->extractors())->toHaveCount(1);
        expect($buffer->extractors()[0])->toBeInstanceOf(DirectJsonExtractor::class);
    });

    it('assembles deltas into raw content', function () {
        $buffer = ExtractingJsonBuffer::empty();

        $buffer = $buffer->assemble('{"na');
        expect($buffer->raw())->toBe('{"na');

        $buffer = $buffer->assemble('me":"');
        expect($buffer->raw())->toBe('{"name":"');

        $buffer = $buffer->assemble('John"}');
        expect($buffer->raw())->toBe('{"name":"John"}');
    });

    it('normalizes complete JSON using strategy', function () {
        $buffer = ExtractingJsonBuffer::empty();

        $buffer = $buffer->assemble('{"name":"John"}');

        expect($buffer->normalized())->toBe('{"name":"John"}');
    });

    it('normalizes incomplete JSON using partial parser fallback', function () {
        $buffer = ExtractingJsonBuffer::empty();

        $buffer = $buffer->assemble('{"name":"Jo');

        // Partial parser should complete the incomplete JSON
        expect($buffer->normalized())->toContain('"name"');
    });

    it('skips empty deltas', function () {
        $buffer = ExtractingJsonBuffer::empty();

        $buffer = $buffer->assemble('{"name":"John"}');
        $original = $buffer->raw();

        $buffer = $buffer->assemble('');
        expect($buffer->raw())->toBe($original);

        $buffer = $buffer->assemble('   ');
        expect($buffer->raw())->toBe($original);
    });

    it('skips normalization until structural characters present', function () {
        $buffer = ExtractingJsonBuffer::empty();

        $buffer = $buffer->assemble('Hello');
        expect($buffer->normalized())->toBe('');

        $buffer = $buffer->assemble(' {');
        expect($buffer->raw())->toBe('Hello {');
        // Now normalization should happen
    });

    it('handles JSON with trailing comma via resilient extractor', function () {
        $buffer = ExtractingJsonBuffer::empty([
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
        ]);

        $buffer = $buffer->assemble('{"name":"John",}');

        // DirectJson will fail, ResilientJson should succeed
        $normalized = $buffer->normalized();
        expect($normalized)->toContain('"name"');
    });

    it('returns immutable instances', function () {
        $buffer = ExtractingJsonBuffer::empty();
        $buffer2 = $buffer->assemble('{"test":true}');

        expect($buffer)->not->toBe($buffer2);
        expect($buffer->raw())->toBe('');
        expect($buffer2->raw())->toBe('{"test":true}');
    });

    it('equals compares normalized content', function () {
        $buffer1 = ExtractingJsonBuffer::empty()->assemble('{"a":1}');
        $buffer2 = ExtractingJsonBuffer::empty()->assemble('{"a":1}');
        $buffer3 = ExtractingJsonBuffer::empty()->assemble('{"b":2}');

        expect($buffer1->equals($buffer2))->toBeTrue();
        expect($buffer1->equals($buffer3))->toBeFalse();
    });

    it('handles streaming chunks progressively', function () {
        $buffer = ExtractingJsonBuffer::empty();

        // Simulate streaming chunks
        $chunks = ['{"us', 'er":', '{"na', 'me":', '"John', '"}}'];

        foreach ($chunks as $chunk) {
            $buffer = $buffer->assemble($chunk);
        }

        expect($buffer->raw())->toBe('{"user":{"name":"John"}}');
        expect($buffer->normalized())->toBe('{"user":{"name":"John"}}');
    });

    it('uses default extractors when null passed', function () {
        $buffer = ExtractingJsonBuffer::empty(null);

        $defaultExtractors = ExtractingJsonBuffer::defaultExtractors();
        expect(count($buffer->extractors()))->toBe(count($defaultExtractors));
    });

    it('provides default extractors optimized for streaming', function () {
        $extractors = ExtractingJsonBuffer::defaultExtractors();

        // Should have Direct and Resilient for streaming
        expect($extractors)->toHaveCount(2);
        expect($extractors[0])->toBeInstanceOf(DirectJsonExtractor::class);
        expect($extractors[1])->toBeInstanceOf(ResilientJsonExtractor::class);
    });
});
