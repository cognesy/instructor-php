<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Utils\Profiler\ObjectCreationTrace;
use PHPUnit\Framework\TestCase;

final class InferenceStreamingObjectProfile1000Test extends TestCase
{
    protected function tearDown(): void
    {
        ObjectCreationTrace::reset();
    }

    public function testInferenceStreamingObjectProfileMatchesFixtureFor1000Chunks(): void
    {
        gc_collect_cycles();

        ObjectCreationTrace::enable(self::trackedClasses());

        $request = (new InferenceRequestBuilder())
            ->withMessages('Stream the payload.')
            ->withStreaming()
            ->create();

        $pending = new PendingInference(
            execution: InferenceExecution::fromRequest($request),
            driver: new FakeInferenceDriver(
                onStream: static fn() => self::partials(),
            ),
            eventDispatcher: new EventDispatcher(),
        );

        $emissions = 0;
        foreach ($pending->stream()->deltas() as $delta) {
            $emissions += 1;
            self::assertInstanceOf(PartialInferenceDelta::class, $delta);
        }

        $final = $pending->response();
        $report = [
            'chunks' => 1000,
            'emissions' => $emissions,
            'finalContentLength' => strlen($final->content()),
            'finalCreatedByClass' => ObjectCreationTrace::createdByClass(),
            'finalLiveByClass' => ObjectCreationTrace::liveByClass(),
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
            InferenceAttempt::class,
            InferenceExecution::class,
            InferenceResponse::class,
            InferenceStreamState::class,
            PartialInferenceDelta::class,
            Usage::class,
        ];
    }

    /**
     * @return iterable<PartialInferenceResponse>
     */
    private static function partials(): iterable
    {
        $payload = self::payload();
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            yield new PartialInferenceResponse(
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
        return str_repeat('a', 1000);
    }
}
