<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Extraction\Buffers\JsonBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularUpdateGenerator;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\EnrichResponseReducer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\ExtractDeltaReducer;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Tests\Support\FakeStreamFactory;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

final class ModularPipelineMentalModelRegressionModel
{
    public string $test = '';
}

function mmr_makeResponseModelAndGenerator(FakeInferenceDriver $driver, EventDispatcher $events): array {
    $config = new StructuredOutputConfig();
    $schemaRenderer = new StructuredOutputSchemaRenderer($config);
    $responseModelFactory = new ResponseModelFactory($schemaRenderer, $config, $events);

    $llmProvider = LLMProvider::using('openai')->withDriver($driver);
    $inferenceProvider = new InferenceProvider(
        InferenceRuntime::fromProvider($llmProvider),
        new RequestMaterializer(),
    );

    $deserializer = new ResponseDeserializer($events, [SymfonyDeserializer::class], $config);
    $transformer = new ResponseTransformer(events: $events, transformers: [], config: $config);

    return [
        new ModularUpdateGenerator(
            inferenceProvider: $inferenceProvider,
            deserializer: $deserializer,
            transformer: $transformer,
            events: $events,
            bufferFactory: null,
        ),
        $responseModelFactory->fromAny(ModularPipelineMentalModelRegressionModel::class),
        $config,
    ];
}

function mmr_makeExecution(ResponseModel $responseModel, StructuredOutputConfig $config, OutputMode $mode): StructuredOutputExecution {
    return (new StructuredOutputExecution())->with(
        request: new StructuredOutputRequest(
            messages: [['role' => 'user', 'content' => 'Test']],
            options: ['stream' => true],
        ),
        responseModel: $responseModel,
        config: $config->with(outputMode: $mode),
    );
}

function mmr_makeCollectorReducer(): Reducer {
    return new class implements Reducer {
        public array $items = [];

        public function init(): mixed {
            $this->items = [];
            return null;
        }

        public function step(mixed $accumulator, mixed $reducible): mixed {
            $this->items[] = $reducible;
            return $reducible;
        }

        public function complete(mixed $accumulator): mixed {
            return $this->items;
        }
    };
}

test('modular pipeline dispatches StreamedResponseReceived when stream completes', function () {
    $events = new EventDispatcher();
    $seenEvents = [];
    $events->wiretap(static function (object $event) use (&$seenEvents): void {
        $seenEvents[] = $event;
    });

    $driver = new FakeInferenceDriver(streamBatches: [[
        new PartialInferenceResponse(contentDelta: '{"test":"value"}', finishReason: 'stop', usage: Usage::none()),
    ]]);

    [$generator, $responseModel, $config] = mmr_makeResponseModelAndGenerator($driver, $events);
    $execution = mmr_makeExecution($responseModel, $config, OutputMode::JsonSchema);

    $execution = $generator->nextChunk($execution); // initialize stream

    while ($generator->hasNext($execution)) {
        $execution = $generator->nextChunk($execution);
    }

    $completionEvents = array_filter(
        $seenEvents,
        static fn(object $event): bool => $event instanceof StreamedResponseReceived,
    );

    expect($completionEvents)->toHaveCount(1);
});

test('extract delta stage uses cumulative snapshot content, not raw per-chunk delta', function () {
    $collector = mmr_makeCollectorReducer();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);
    $reducer->init();

    [$first, $second] = FakeStreamFactory::from(
        new PartialInferenceResponse(contentDelta: '{"test":"va', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: 'lue"}', usage: Usage::none()),
    );

    $reducer->step(null, $first);
    $reducer->step(null, $second);

    expect($collector->items)->toHaveCount(2)
        ->and($collector->items[0]->buffer->raw())->toBe('{"test":"va')
        ->and($collector->items[1]->buffer->raw())->toBe('{"test":"value"}');
});

test('enrich stage is mode-specific: tools use normalized buffer, json uses source content', function () {
    $object = (object) ['test' => 'value'];
    $source = (new PartialInferenceResponse(contentDelta: 'noise', usage: Usage::none()))
        ->withContent('raw source content');

    $frame = PartialFrame::fromResponse($source)
        ->withBuffer(JsonBuffer::empty()->assemble('{"test":"value"}'))
        ->withObject(Result::success($object));

    $toolsCollector = mmr_makeCollectorReducer();
    $toolsReducer = new EnrichResponseReducer(inner: $toolsCollector, mode: OutputMode::Tools);
    $toolsReducer->init();
    $toolsReducer->step(null, $frame);

    $jsonCollector = mmr_makeCollectorReducer();
    $jsonReducer = new EnrichResponseReducer(inner: $jsonCollector, mode: OutputMode::JsonSchema);
    $jsonReducer->init();
    $jsonReducer->step(null, $frame);

    expect($toolsCollector->items)->toHaveCount(1)
        ->and($toolsCollector->items[0]->content())->toBe('{"test":"value"}')
        ->and($toolsCollector->items[0]->value())->toEqual($object);

    expect($jsonCollector->items)->toHaveCount(1)
        ->and($jsonCollector->items[0]->content())->toBe('raw source content')
        ->and($jsonCollector->items[0]->value())->toEqual($object);
});
