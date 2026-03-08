<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extraction\Buffers\ExtractingBuffer;
use Cognesy\Instructor\Extraction\Buffers\JsonBuffer;
use Cognesy\Instructor\Extraction\Buffers\TextBuffer;
use Cognesy\Instructor\Extraction\Buffers\ToolsBuffer;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;
use Cognesy\Utils\Profiler\ObjectCreationTrace;
use PHPUnit\Framework\TestCase;

final class StructuredStreamingObjectProfile1000Test extends TestCase
{
    protected function tearDown(): void
    {
        ObjectCreationTrace::reset();
    }

    public function testStructuredOutputStreamingObjectProfileMatchesFixtureFor1000Chunks(): void
    {
        gc_collect_cycles();

        ObjectCreationTrace::enable(self::trackedClasses());

        $stream = (new \Cognesy\Instructor\StructuredOutput())
            ->withRuntime(makeStructuredRuntime(
                driver: new FakeInferenceDriver(
                    onStream: static fn() => self::deltas(),
                ),
                outputMode: OutputMode::Json,
            ))
            ->with(
                messages: 'Extract the streamed payload.',
                responseModel: StreamProfilePayload1000::class,
            )
            ->withStreaming(true)
            ->stream();

        $emissions = 0;
        foreach ($stream->responses() as $response) {
            $emissions += 1;
            self::assertInstanceOf(StructuredOutputResponse::class, $response);
        }

        $final = $stream->finalResponse();
        $value = $final->value();
        $report = [
            'chunks' => 1000,
            'emissions' => $emissions,
            'finalContentLength' => strlen($final->content()),
            'finalCreatedByClass' => ObjectCreationTrace::createdByClass(),
            'finalLiveByClass' => ObjectCreationTrace::liveByClass(),
            'finalValueType' => get_debug_type($value),
            'finalTextLength' => match (true) {
                $value instanceof StreamProfilePayload1000 => strlen($value->text),
                default => 0,
            },
        ];

        $json = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $fixture = __DIR__ . '/../Fixtures/streaming_object_profile_1000.json';

        if (getenv('UPDATE_OBJECT_PROFILE_FIXTURE') === '1') {
            file_put_contents($fixture, $json);
        }

        $this->assertJsonStringEqualsJsonFile($fixture, $json);
    }

    /**
     * @return list<string>
     */
    private static function trackedClasses(): array
    {
        return [
            StructuredOutputExecution::class,
            StructuredOutputAttempt::class,
            StructuredOutputResponse::class,
            StructuredOutputStreamState::class,
            ExtractionInput::class,
            ExtractingBuffer::class,
            TextBuffer::class,
            JsonBuffer::class,
            ToolsBuffer::class,
            InferenceResponse::class,
            InferenceAttempt::class,
            InferenceExecution::class,
            InferenceStreamState::class,
            PartialInferenceDelta::class,
            Usage::class,
        ];
    }

    /**
     * @return iterable<PartialInferenceDelta>
     */
    private static function deltas(): iterable
    {
        $payload = self::payload();
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            yield new PartialInferenceDelta(
                contentDelta: $payload[$index],
                finishReason: match ($index === $length - 1) {
                    true => 'stop',
                    default => '',
                },
            );
        }
    }

    private static function payload(): string
    {
        return '{"text":"' . str_repeat('a', 989) . '"}';
    }
}

final class StreamProfilePayload1000
{
    public string $text;
}
