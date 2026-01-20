<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Extraction\Buffers\ExtractingBuffer;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

describe('ExtractingBuffer', function () {
    it('creates empty buffer with default extractors', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        expect($buffer->raw())->toBe('');
        expect($buffer->normalized())->toBe('');
        expect($buffer->isEmpty())->toBeTrue();
        expect($buffer->extractors())->toHaveCount(3);
    });

    it('creates buffer with custom extractors', function () {
        $buffer = ExtractingBuffer::withExtractors(OutputMode::Json, new DirectJsonExtractor());

        expect($buffer->extractors())->toHaveCount(1);
        expect($buffer->extractors()[0])->toBeInstanceOf(DirectJsonExtractor::class);
    });

    it('assembles deltas into raw content', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        $buffer = $buffer->assemble('{"na');
        expect($buffer->raw())->toBe('{"na');

        $buffer = $buffer->assemble('me":"');
        expect($buffer->raw())->toBe('{"name":"');

        $buffer = $buffer->assemble('John"}');
        expect($buffer->raw())->toBe('{"name":"John"}');
    });

    it('normalizes complete JSON using strategy', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        $buffer = $buffer->assemble('{"name":"John"}');

        expect($buffer->normalized())->toBe('{"name":"John"}');
    });

    it('normalizes incomplete JSON using partial parser fallback', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        $buffer = $buffer->assemble('{"name":"Jo');

        // Partial parser should complete the incomplete JSON
        expect($buffer->normalized())->toContain('"name"');
    });

    it('skips empty deltas', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        $buffer = $buffer->assemble('{"name":"John"}');
        $original = $buffer->raw();

        $buffer = $buffer->assemble('');
        expect($buffer->raw())->toBe($original);

        $buffer = $buffer->assemble('   ');
        expect($buffer->raw())->toBe($original);
    });

    it('skips normalization until structural characters present', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        $buffer = $buffer->assemble('Hello');
        expect($buffer->normalized())->toBe('');

        $buffer = $buffer->assemble(' {');
        expect($buffer->raw())->toBe('Hello {');
        // Now normalization should happen
    });

    it('handles JSON with trailing comma via resilient extractor', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json, [
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
        ]);

        $buffer = $buffer->assemble('{"name":"John",}');

        // DirectJson will fail, ResilientJson should succeed
        $normalized = $buffer->normalized();
        expect($normalized)->toContain('"name"');
    });

    it('returns immutable instances', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);
        $buffer2 = $buffer->assemble('{"test":true}');

        expect($buffer)->not->toBe($buffer2);
        expect($buffer->raw())->toBe('');
        expect($buffer2->raw())->toBe('{"test":true}');
    });

    it('equals compares normalized content', function () {
        $buffer1 = ExtractingBuffer::empty(OutputMode::Json)->assemble('{"a":1}');
        $buffer2 = ExtractingBuffer::empty(OutputMode::Json)->assemble('{"a":1}');
        $buffer3 = ExtractingBuffer::empty(OutputMode::Json)->assemble('{"b":2}');

        expect($buffer1->equals($buffer2))->toBeTrue();
        expect($buffer1->equals($buffer3))->toBeFalse();
    });

    it('handles streaming chunks progressively', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json);

        // Simulate streaming chunks
        $chunks = ['{"us', 'er":', '{"na', 'me":', '"John', '"}}'];

        foreach ($chunks as $chunk) {
            $buffer = $buffer->assemble($chunk);
        }

        expect($buffer->raw())->toBe('{"user":{"name":"John"}}');
        expect($buffer->normalized())->toBe('{"user":{"name":"John"}}');
    });

    it('uses default extractors when null passed', function () {
        $buffer = ExtractingBuffer::empty(OutputMode::Json, null);

        $defaultExtractors = ExtractingBuffer::defaultExtractors();
        expect(count($buffer->extractors()))->toBe(count($defaultExtractors));
    });

    it('provides default extractors optimized for streaming', function () {
        $extractors = ExtractingBuffer::defaultExtractors();

        // Should have Direct, Resilient, and Partial for streaming
        expect($extractors)->toHaveCount(3);
        expect($extractors[0])->toBeInstanceOf(DirectJsonExtractor::class);
        expect($extractors[1])->toBeInstanceOf(ResilientJsonExtractor::class);
    });
});
