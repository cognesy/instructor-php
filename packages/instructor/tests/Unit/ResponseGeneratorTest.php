<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\Result\Result;

// Lightweight fakes for pipeline dependencies
class FakeDeserializer extends ResponseDeserializer {
    public function __construct($events, $config) { parent::__construct($events, [], $config); }
    public function deserialize(string $text, ResponseModel $responseModel, ?string $toolName = null) : Result {
        return Result::success((object)['ok' => true, 'json' => $text]);
    }
}
class FakeValidator extends ResponseValidator {
    public function __construct($events, $config) { parent::__construct($events, [], $config); }
}
class FakeTransformer extends ResponseTransformer {
    public function __construct($events, $config) { parent::__construct($events, [], $config); }
}

describe('ResponseGenerator', function () {
    function makeResponseModelForStd(): ResponseModel {
        $cfg = new StructuredOutputConfig();
        $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
        $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
        return $factory->fromAny(stdClass::class);
    }

    it('succeeds for valid JSON and propagates through pipeline', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(fn($e) => $e);
        $config = new StructuredOutputConfig();
        $gen = new ResponseGenerator(
            responseDeserializer: new FakeDeserializer($events, $config),
            responseValidator: new FakeValidator($events, $config),
            responseTransformer: new FakeTransformer($events, $config),
            events: $events,
        );

        $resp = new InferenceResponse(content: '{"a":1}');
        $result = $gen->makeResponse($resp, makeResponseModelForStd(), OutputMode::Json);

        expect($result->isSuccess())->toBeTrue();
        $obj = $result->unwrap();
        expect($obj->ok)->toBeTrue();
    });

    it('fails when no JSON is present', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(fn($e) => $e);
        $config = new StructuredOutputConfig();
        $gen = new ResponseGenerator(
            responseDeserializer: new FakeDeserializer($events, $config),
            responseValidator: new FakeValidator($events, $config),
            responseTransformer: new FakeTransformer($events, $config),
            events: $events,
        );

        $resp = new InferenceResponse(content: '');
        $result = $gen->makeResponse($resp, makeResponseModelForStd(), OutputMode::Json);
        expect($result->isFailure())->toBeTrue();
        expect($result->errorMessage())->toContain('No JSON');
    });
});
