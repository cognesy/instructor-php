<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

class NormalizedPayloadUser
{
    public string $name = 'Ava';
    public int $age = 34;
}

class SelfValidatingNormalizedPayload implements CanValidateSelf
{
    public string $name = 'Ava';

    public function validate(): ValidationResult
    {
        return ValidationResult::valid();
    }
}

class PassingObjectValidator implements CanValidateObject
{
    public function validate(object $dataObject): ValidationResult
    {
        return ValidationResult::valid();
    }
}

class SelfTransformingNormalizedPayload implements CanTransformSelf
{
    public string $name = 'Ava';

    public function transform(): mixed
    {
        return 42;
    }
}

class FailingTransformer implements CanTransformData
{
    public function transform(mixed $data): mixed
    {
        throw new RuntimeException('transform failed');
    }
}

class ThrowingExtractor implements CanExtractResponse
{
    public function extract(ExtractionInput $input): array
    {
        throw new RuntimeException('extract failed');
    }

    public function name(): string
    {
        return 'throwing';
    }
}

function assertNormalizedEventPayload(mixed $value): void
{
    if (is_object($value)) {
        throw new RuntimeException('Event payload contains object: ' . $value::class);
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $nested) {
        assertNormalizedEventPayload($nested);
    }
}

it('emits normalized response model payload summaries', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseModelRequested::class, function ($event) use (&$captured): void {
        $captured['requested'] = $event->data;
    });
    $events->addListener(ResponseModelBuilt::class, function ($event) use (&$captured): void {
        $captured['built'] = $event->data;
    });

    $config = new StructuredOutputConfig();
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );

    $factory->fromAny(NormalizedPayloadUser::class);

    expect($captured['requested'])->toHaveKeys(['requestedType', 'requestedClass']);
    expect($captured['built'])->toHaveKeys(['responseClass', 'schemaName', 'propertyCount', 'returnTarget']);
    assertNormalizedEventPayload($captured['requested']);
    assertNormalizedEventPayload($captured['built']);
});

it('emits normalized validation payloads', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseValidationAttempt::class, function ($event) use (&$captured): void {
        $captured['attempt'] = $event->data;
    });
    $events->addListener(ResponseValidated::class, function ($event) use (&$captured): void {
        $captured['validated'] = $event->data;
    });
    $events->addListener(CustomResponseValidationAttempt::class, function ($event) use (&$captured): void {
        $captured['custom'] = $event->data;
    });

    $validator = new ResponseValidator(
        events: $events,
        validator: new PassingObjectValidator(),
        config: new StructuredOutputConfig(),
    );

    $validator->validate(new NormalizedPayloadUser(), makeAnyResponseModel(NormalizedPayloadUser::class));
    $validator->validate(new SelfValidatingNormalizedPayload(), makeAnyResponseModel(SelfValidatingNormalizedPayload::class));

    expect($captured['attempt'])->toHaveKeys(['responseClass', 'fieldCount']);
    expect($captured['validated']['validation'])->toHaveKeys(['isValid', 'message', 'errors']);
    expect($captured['custom'])->toHaveKeys(['responseClass', 'fieldCount']);
    assertNormalizedEventPayload($captured['attempt']);
    assertNormalizedEventPayload($captured['validated']);
    assertNormalizedEventPayload($captured['custom']);
});

it('emits normalized transformation payloads', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseTransformationAttempt::class, function ($event) use (&$captured): void {
        $captured['attempt'][] = $event->data;
    });
    $events->addListener(ResponseTransformed::class, function ($event) use (&$captured): void {
        $captured['transformed'] = $event->data;
    });
    $events->addListener(ResponseTransformationFailed::class, function ($event) use (&$captured): void {
        $captured['failed'] = $event->data;
    });

    $selfTransformer = new ResponseTransformer(
        events: $events,
        transformer: null,
        config: new StructuredOutputConfig(),
    );
    $selfTransformer->transform(new SelfTransformingNormalizedPayload(), makeAnyResponseModel(SelfTransformingNormalizedPayload::class));

    $failingTransformer = new ResponseTransformer(
        events: $events,
        transformer: new FailingTransformer(),
        config: new StructuredOutputConfig(),
    );
    $failingTransformer->transform(['name' => 'Ava'], makeAnyResponseModel(\stdClass::class));

    expect($captured['attempt'][0])->toHaveKeys(['valueType', 'fieldCount']);
    expect($captured['transformed'])->toHaveKey('valueType');
    expect($captured['failed'])->toHaveKeys(['valueType', 'itemCount', 'keys', 'errorMessage', 'errorType']);
    assertNormalizedEventPayload($captured['attempt']);
    assertNormalizedEventPayload($captured['transformed']);
    assertNormalizedEventPayload($captured['failed']);
});

it('emits normalized response generation failures', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseGenerationFailed::class, function ($event) use (&$captured): void {
        $captured = $event->data;
    });

    $config = new StructuredOutputConfig();
    $generator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, new SymfonyDeserializer(), $config),
        responseValidator: new ResponseValidator($events, new PassingObjectValidator(), $config),
        responseTransformer: new ResponseTransformer($events, null, $config),
        events: $events,
        extractor: new ThrowingExtractor(),
    );

    $result = $generator->makeResponse(
        response: new InferenceResponse(content: '{"name":"Ava"}'),
        responseModel: makeAnyResponseModel(NormalizedPayloadUser::class),
        mode: OutputMode::Json,
    );

    expect($result->isFailure())->toBeTrue();
    expect($captured)->toHaveKeys(['errorMessage', 'errorType']);
    assertNormalizedEventPayload($captured);
});
