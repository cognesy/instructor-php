<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Partials\PartialStreamFactory;
use Cognesy\Instructor\Executors\Partials\PartialStreamingUpdateGenerator;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\Executors\Streaming\StreamingUpdatesGenerator;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

final class StructuredOutputStreamingBench
{
    private function make1KBStream(): array
    {
        $open = '{"list":[';
        $close = ']}';
        $items = [];
        $size = strlen($open) + strlen($close);
        $i = 0;
        while ($size < 1024) {
            $txt = str_repeat('x', 16);
            $item = sprintf('{"i":%d,"t":"%s"}', $i, $txt);
            $sep = ($i === 0) ? '' : ',';
            $items[] = $sep . $item;
            $size += strlen($sep . $item);
            $i++;
        }

        $chunks = [];
        $chunks[] = new PartialInferenceResponse(contentDelta: $open);
        foreach ($items as $piece) {
            $chunks[] = new PartialInferenceResponse(contentDelta: $piece);
        }
        $chunks[] = new PartialInferenceResponse(contentDelta: $close, finishReason: 'stop');
        return $chunks;
    }

    private function make1KBJson(): string
    {
        $content = '{"list":[';
        $i = 0;
        while (strlen($content) < 1024) {
            $txt = str_repeat('x', 16);
            $piece = sprintf('%s{"i":%d,"t":"%s"}', $i === 0 ? '' : ',', $i, $txt);
            $content .= $piece;
            $i++;
        }
        return $content . ']}';
    }

    private function responseModelForSequence(): mixed
    {
        // Use Sequence<stdClass> to avoid extra class declarations in this file
        return new Sequence(\stdClass::class);
    }

    /**
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchPartialsStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [ $this->make1KBStream() ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();
    }

    /**
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchLegacyStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [ $this->make1KBStream() ]);
        $events = new EventDispatcher();
        $llm = LLMProvider::new()->withDriver($driver);

        $cfg = new StructuredOutputConfig();
        $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
        $responseFactory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, $events);
        $responseModel = $responseFactory->fromAny($this->responseModelForSequence());

        $inferenceProvider = new InferenceProvider($llm, new RequestMaterializer(), $events);

        $partialsGenerator = new GeneratePartialsFromJson(
            responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], $cfg),
            partialValidator: new PartialValidation(new \Cognesy\Instructor\Config\PartialsGeneratorConfig()),
            responseTransformer: new ResponseTransformer($events, [], $cfg),
            events: $events
        );

        $generator = new StreamingUpdatesGenerator(
            inferenceProvider: $inferenceProvider,
            partialsGenerator: $partialsGenerator
        );

        $execution = (new StructuredOutputExecution())
            ->with(
                request: (new StructuredOutputRequest())
                    ->withMessages([[ 'role' => 'user', 'content' => 'Emit seq' ]])
                    ->withStreamed(true),
                responseModel: $responseModel,
                config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
            );

        while ($generator->hasNext($execution)) {
            $execution = $generator->nextChunk($execution);
        }
    }

    /**
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchSync1KB(): void
    {
        $json = $this->make1KBJson();
        $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $result = $so->get();
    }
}
