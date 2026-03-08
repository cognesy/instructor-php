<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Streaming\Sequence\SequenceTracker;
use Cognesy\Instructor\Streaming\Sequence\SequenceTrackingResult;
use Cognesy\Instructor\Streaming\Sequence\SequenceUpdateList;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Tests\Support\ObservedObjectGraph;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Profiler\ObjectCreationSnapshot;
use Cognesy\Utils\Profiler\ObjectCreationTrace;
use PHPUnit\Framework\TestCase;

final class StreamingObjectProfileTest extends TestCase
{
    protected function tearDown(): void
    {
        ObjectCreationTrace::reset();
    }

    public function testStreamingObjectProfileMatchesFixture(): void
    {
        gc_collect_cycles();

        ObjectCreationTrace::enable(self::trackedClasses());

        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(
                driver: new FakeInferenceDriver(streamBatches: [self::chunks()]),
                outputMode: OutputMode::Json,
                transformers: [StreamProfileNoOpTransformer::class],
            ))
            ->with(
                messages: 'Extract the streamed list.',
                responseModel: Sequence::of(StreamProfileItem::class),
            )
            ->withStreaming(true)
            ->stream();

        $samples = [];
        $graphs = [];

        $samples[] = ObjectCreationTrace::sample('before-sequence');
        foreach ($stream->sequence() as $index => $item) {
            $label = 'item-' . ($index + 1);
            $samples[] = ObjectCreationTrace::sample($label);
            $graphs[$label] = ObservedObjectGraph::summarize(
                value: $item,
                classes: [StreamProfileItem::class, Sequence::class],
            );
        }

        $samples[] = ObjectCreationTrace::sample('after-sequence');
        $stream->finalResponse();
        $finalValue = $stream->finalValue();
        $samples[] = ObjectCreationTrace::sample('after-final-response');
        $graphs['after-final-response'] = ObservedObjectGraph::summarize(
            value: $finalValue,
            classes: [StreamProfileItem::class, Sequence::class],
        );

        $report = [
            'finalCreatedByClass' => ObjectCreationTrace::createdByClass(),
            'finalLiveByClass' => ObjectCreationTrace::liveByClass(),
            'samples' => array_map(
                fn(ObjectCreationSnapshot $sample): array => $this->normalizeSample($sample, $graphs),
                $samples,
            ),
        ];

        $json = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $fixture = __DIR__ . '/../Fixtures/streaming_object_profile.json';

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
            SequenceTracker::class,
            SequenceTrackingResult::class,
            SequenceUpdateList::class,
            ValidationResult::class,
            InferenceResponse::class,
            InferenceAttempt::class,
            InferenceExecution::class,
            Usage::class,
        ];
    }

    /**
     * @return PartialInferenceResponse[]
     */
    private static function chunks(): array
    {
        return [
            new PartialInferenceResponse(contentDelta: '{"list":[{"id":1,"name":"alpha"}'),
            new PartialInferenceResponse(contentDelta: ',{"id":2,"name":"beta"}'),
            new PartialInferenceResponse(contentDelta: ',{"id":3,"name":"gamma"}'),
            new PartialInferenceResponse(contentDelta: ']}', finishReason: 'stop'),
        ];
    }

    /**
     * @param array<string, array<string, int>> $graphs
     * @return array<string, mixed>
     */
    private function normalizeSample(ObjectCreationSnapshot $sample, array $graphs): array
    {
        return [
            'label' => $sample->label,
            'createdTotal' => $sample->createdTotal,
            'liveTotal' => $sample->liveTotal,
            'createdByClass' => $sample->createdByClass,
            'liveByClass' => $sample->liveByClass,
            'observedOutputGraph' => $graphs[$sample->label] ?? [],
        ];
    }
}

final class StreamProfileItem
{
    public int $id;
    public string $name;
}

final class StreamProfileNoOpTransformer implements CanTransformData
{
    public function transform(mixed $data): mixed
    {
        return $data;
    }
}
