<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Extraction\Buffers\ExtractingJsonBuffer;
use Cognesy\Instructor\Extraction\Buffers\JsonBuffer;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Extractors\BracketMatchingExtractor;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\ExtractDeltaReducer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Helper to collect frames during streaming.
 */
function makeStreamingFrameCollector(): Reducer
{
    return new class implements Reducer {
        public array $collected = [];

        public function init(): mixed
        {
            $this->collected = [];
            return null;
        }

        public function step(mixed $accumulator, mixed $reducible): mixed
        {
            $this->collected[] = $reducible;
            return $reducible;
        }

        public function complete(mixed $accumulator): mixed
        {
            return $this->collected;
        }
    };
}

/**
 * Custom extractor that strips XML wrapper before parsing.
 */
class XmlWrapperExtractor implements CanExtractContent
{
    #[\Override]
    public function extract(string $content): Result
    {
        // Pattern: <json>{"key": "value"}</json>
        if (preg_match('/<json>(.*?)<\/json>/s', $content, $matches)) {
            $json = trim($matches[1]);
            try {
                json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException $e) {
                return Result::failure("Invalid JSON in XML wrapper: {$e->getMessage()}");
            }
        }
        return Result::failure('No <json> wrapper found');
    }

    #[\Override]
    public function name(): string
    {
        return 'xml_wrapper';
    }
}

/**
 * Custom extractor that extracts from YAML-like delimiter.
 */
class DelimiterExtractor implements CanExtractContent
{
    public function __construct(
        private string $startDelimiter = '---JSON_START---',
        private string $endDelimiter = '---JSON_END---',
    ) {}

    #[\Override]
    public function extract(string $content): Result
    {
        $start = strpos($content, $this->startDelimiter);
        $end = strpos($content, $this->endDelimiter);

        if ($start === false || $end === false || $end <= $start) {
            return Result::failure('Delimiters not found');
        }

        $json = trim(substr(
            $content,
            $start + strlen($this->startDelimiter),
            $end - $start - strlen($this->startDelimiter)
        ));

        try {
            json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (\JsonException $e) {
            return Result::failure("Invalid JSON between delimiters: {$e->getMessage()}");
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'delimiter';
    }
}

describe('Streaming-Aware Extraction', function () {
    describe('ExtractDeltaReducer with buffer factory', function () {
        it('uses default JsonBuffer when no factory provided', function () {
            $collector = makeStreamingFrameCollector();
            $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

            $reducer->init();
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '{"name":"John"}',
                usage: Usage::none(),
            ));

            expect($collector->collected[0]->buffer)->toBeInstanceOf(JsonBuffer::class);
        });

        it('uses custom buffer from factory', function () {
            $collector = makeStreamingFrameCollector();
            $factory = fn(OutputMode $mode) => ExtractingJsonBuffer::empty();
            $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema, bufferFactory: $factory);

            $reducer->init();
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '{"name":"John"}',
                usage: Usage::none(),
            ));

            expect($collector->collected[0]->buffer)->toBeInstanceOf(ExtractingJsonBuffer::class);
        });

        it('passes OutputMode to buffer factory', function () {
            $receivedMode = null;
            $collector = makeStreamingFrameCollector();
            $factory = function (OutputMode $mode) use (&$receivedMode): CanBufferContent {
                $receivedMode = $mode;
                return ExtractingJsonBuffer::empty();
            };

            $reducer = new ExtractDeltaReducer(
                inner: $collector,
                mode: OutputMode::MdJson,
                bufferFactory: $factory,
            );

            $reducer->init();
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '{"test":true}',
                usage: Usage::none(),
            ));

            expect($receivedMode)->toBe(OutputMode::MdJson);
        });
    });

    describe('ExtractingJsonBuffer with custom extractors', function () {
        it('applies custom extractor during streaming', function () {
            $buffer = ExtractingJsonBuffer::withExtractors(new XmlWrapperExtractor());

            $buffer = $buffer->assemble('<json>{"name":"John"}</json>');

            expect($buffer->normalized())->toBe('{"name":"John"}');
        });

        it('applies delimiter extractor during streaming', function () {
            $buffer = ExtractingJsonBuffer::withExtractors(new DelimiterExtractor());

            $buffer = $buffer->assemble('Some text ---JSON_START--- {"id": 42} ---JSON_END--- more text');

            expect($buffer->normalized())->toBe('{"id": 42}');
        });

        it('tries extractors in order until success', function () {
            // DelimiterExtractor will fail, XmlWrapperExtractor will succeed
            $buffer = ExtractingJsonBuffer::withExtractors(
                new DelimiterExtractor(),
                new XmlWrapperExtractor(),
            );

            $buffer = $buffer->assemble('<json>{"found": true}</json>');

            expect($buffer->normalized())->toBe('{"found": true}');
        });

        it('falls back to partial JSON when all extractors fail', function () {
            $buffer = ExtractingJsonBuffer::withExtractors(new XmlWrapperExtractor());

            // No XML wrapper, so extractor fails, falls back to partial parser
            $buffer = $buffer->assemble('{"name":"Jo');

            // Partial parser should complete the incomplete JSON
            expect($buffer->normalized())->toContain('"name"');
        });

        it('accumulates deltas and re-extracts each time', function () {
            $buffer = ExtractingJsonBuffer::withExtractors(new BracketMatchingExtractor());

            // Feed in chunks with surrounding text
            $buffer = $buffer->assemble('Response: {"na');
            expect($buffer->raw())->toBe('Response: {"na');
            // Still incomplete, partial parser used

            $buffer = $buffer->assemble('me":"John"}');
            expect($buffer->raw())->toBe('Response: {"name":"John"}');
            expect($buffer->normalized())->toBe('{"name":"John"}');
        });

        it('works with streaming chunks progressively', function () {
            $buffer = ExtractingJsonBuffer::withExtractors(
                new DirectJsonExtractor(),
                new BracketMatchingExtractor(),
            );

            $chunks = ['Prefix ', '{"user":', '{"name":', '"Alice', '"}}', ' Suffix'];
            foreach ($chunks as $chunk) {
                $buffer = $buffer->assemble($chunk);
            }

            expect($buffer->raw())->toBe('Prefix {"user":{"name":"Alice"}} Suffix');
            // BracketMatchingExtractor should extract the JSON
            expect($buffer->normalized())->toBe('{"user":{"name":"Alice"}}');
        });
    });

    describe('Integration: custom extractors in streaming pipeline', function () {
        it('integrates custom buffer with ExtractDeltaReducer', function () {
            $collector = makeStreamingFrameCollector();
            $factory = fn(OutputMode $mode) => ExtractingJsonBuffer::withExtractors(
                new BracketMatchingExtractor(),
            );

            $reducer = new ExtractDeltaReducer(
                inner: $collector,
                mode: OutputMode::JsonSchema,
                bufferFactory: $factory,
            );

            $reducer->init();

            // Simulate streaming with embedded JSON
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: 'Here is JSON: {"name"',
                usage: Usage::none(),
            ));
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: ':"John"}',
                usage: Usage::none(),
            ));

            // Second frame should have complete extracted JSON
            $finalBuffer = $collector->collected[1]->buffer;
            expect($finalBuffer->raw())->toBe('Here is JSON: {"name":"John"}');
            expect($finalBuffer->normalized())->toBe('{"name":"John"}');
        });

        it('preserves buffer state across streaming frames', function () {
            $collector = makeStreamingFrameCollector();
            $factory = fn(OutputMode $mode) => ExtractingJsonBuffer::withExtractors(
                new XmlWrapperExtractor(),
                new DirectJsonExtractor(),
            );

            $reducer = new ExtractDeltaReducer(
                inner: $collector,
                mode: OutputMode::Json,
                bufferFactory: $factory,
            );

            $reducer->init();

            // First chunk: incomplete
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '<json>{"status"',
                usage: Usage::none(),
            ));

            // Second chunk: completes the XML wrapper
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: ':"ok"}</json>',
                usage: Usage::none(),
            ));

            // XmlWrapperExtractor should now succeed
            expect($collector->collected[1]->buffer->normalized())->toBe('{"status":"ok"}');
        });
    });

    describe('Edge cases', function () {
        it('handles empty extractors array by falling back to partial parser', function () {
            $buffer = ExtractingJsonBuffer::empty([]);

            $buffer = $buffer->assemble('{"name":"John"}');

            // With no extractors, falls back to partial parser
            expect($buffer->normalized())->toBe('{"name":"John"}');
        });

        it('handles extractor that modifies JSON format', function () {
            // Extractor that strips comments before parsing
            $commentStripper = new class implements CanExtractContent {
                #[\Override]
                public function extract(string $content): Result
                {
                    // Remove single-line comments (not real JSON, but some APIs return this)
                    $cleaned = preg_replace('/\/\/.*$/m', '', $content);
                    $cleaned = trim($cleaned);

                    try {
                        json_decode($cleaned, associative: true, flags: JSON_THROW_ON_ERROR);
                        return Result::success($cleaned);
                    } catch (\JsonException $e) {
                        return Result::failure("Invalid: {$e->getMessage()}");
                    }
                }

                #[\Override]
                public function name(): string
                {
                    return 'comment_stripper';
                }
            };

            $buffer = ExtractingJsonBuffer::withExtractors($commentStripper);
            $buffer = $buffer->assemble('{"name":"John"} // this is a comment');

            expect($buffer->normalized())->toBe('{"name":"John"}');
        });

        it('resets buffer state on init', function () {
            $collector = makeStreamingFrameCollector();
            $factory = fn(OutputMode $mode) => ExtractingJsonBuffer::empty();

            $reducer = new ExtractDeltaReducer(
                inner: $collector,
                mode: OutputMode::JsonSchema,
                bufferFactory: $factory,
            );

            // First stream
            $reducer->init();
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '{"stream":1}',
                usage: Usage::none(),
            ));

            // Second stream
            $reducer->init();
            $reducer->step(null, new PartialInferenceResponse(
                contentDelta: '{"stream":2}',
                usage: Usage::none(),
            ));

            // Buffer should be fresh, not accumulated from first stream
            expect($collector->collected[0]->buffer->raw())->toBe('{"stream":2}');
        });
    });
});
