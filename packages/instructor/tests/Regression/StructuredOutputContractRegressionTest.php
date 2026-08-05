<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Events\Response\ResponseMaterialized;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\Exceptions\UnexpectedStructuredOutputTypeException;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Dynamic\Structure;
use Cognesy\Messages\Messages;

final class ContractRegressionDto
{
    public string $status;
}

final class ContractRegressionSelfTarget implements CanDeserializeSelf
{
    public function __construct(
        public readonly string $prefix,
        public string $status = '',
    ) {}

    public function fromArray(array $data): static
    {
        $copy = clone $this;
        $copy->status = $copy->prefix . $data['status'];
        return $copy;
    }
}

final readonly class ContractRegressionNestedDto
{
    public function __construct(
        public int $count,
    ) {}
}

final class ContractRegressionCountingTransformer implements CanTransformData
{
    public int $calls = 0;

    public function transform(mixed $data): mixed
    {
        $this->calls++;
        if (!is_array($data)) {
            throw new UnexpectedValueException('Expected array transformation input.');
        }

        return [...$data, 'transformed' => true];
    }
}

final class ContractRegressionFailOnceTransformer implements CanTransformData
{
    public int $calls = 0;

    public function transform(mixed $data): mixed
    {
        $this->calls++;
        if ($this->calls === 1) {
            throw new RuntimeException('first transformation failed');
        }

        return $data;
    }
}

final class ContractRegressionIdentityTransformer implements CanTransformData
{
    public int $calls = 0;

    public function transform(mixed $data): mixed
    {
        $this->calls++;
        return $data;
    }
}

final class ContractRegressionNullTransformer implements CanTransformData
{
    public int $calls = 0;

    public function transform(mixed $data): mixed
    {
        $this->calls++;
        return null;
    }
}

final class ContractRegressionCallCounter
{
    public int $calls = 0;
}

final class ContractRegressionSelfTransformer implements CanDeserializeSelf, CanTransformSelf
{
    public function __construct(
        private readonly ContractRegressionCallCounter $counter,
        public string $status = '',
    ) {}

    public function fromArray(array $data): static
    {
        $copy = clone $this;
        $copy->status = (string) ($data['status'] ?? '');
        return $copy;
    }

    public function transform(): mixed
    {
        $this->counter->calls++;
        return ['status' => $this->status, 'self_transformed' => true];
    }
}

function contractRegressionOutput(string $content = '{"status":"open"}'): StructuredOutput
{
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: $content),
    ]);

    return (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json));
}

function contractRegressionSchema(?string $rootClass = null): array
{
    return array_filter([
        'type' => 'object',
        'x-php-class' => $rootClass,
        'properties' => [
            'status' => [
                'type' => 'string',
                'enum' => ['open', 'closed'],
            ],
        ],
        'required' => ['status'],
    ], static fn(mixed $value): bool => $value !== null);
}

function contractRegressionForTarget(StructuredOutput $output, string $target): StructuredOutput
{
    $responseModel = match ($target) {
        'structure' => Structure::fromSchema(
            SchemaBuilder::define('issue')
                ->option('status', ['open', 'closed'])
                ->schema(),
        ),
        default => contractRegressionSchema(),
    };
    $request = $output->with(
        messages: 'The issue is open.',
        responseModel: $responseModel,
    );

    return match ($target) {
        'array', 'structure' => $request,
        'stdClass' => $request->intoStdClass(),
        'class' => $request->intoInstanceOf(ContractRegressionDto::class),
        'self' => $request->intoSelfDeserializing(new ContractRegressionSelfTarget('state:')),
        default => throw new InvalidArgumentException("Unknown contract target: {$target}"),
    };
}

it('returns an associative array for a plain enum schema without x-php-class', function () {
    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    expect($result)->toBe(['status' => 'open']);
})->group('structured-output-contract-regression');

it('honors an explicit class target for a plain schema', function () {
    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->intoInstanceOf(ContractRegressionDto::class)
        ->get();

    expect($result)
        ->toBeInstanceOf(ContractRegressionDto::class)
        ->status->toBe('open');
})->group('structured-output-contract-regression');

it('honors the exact self-deserializing target instance for a plain schema', function () {
    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->intoSelfDeserializing(new ContractRegressionSelfTarget(prefix: 'state:'))
        ->get();

    expect($result)
        ->toBeInstanceOf(ContractRegressionSelfTarget::class)
        ->prefix->toBe('state:')
        ->status->toBe('state:open');
})->group('structured-output-contract-regression');

it('hydrates root x-php-class stdClass as a populated stdClass', function () {
    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema(stdClass::class))
        ->get();

    expect($result)
        ->toBeInstanceOf(stdClass::class)
        ->status->toBe('open');
})->group('structured-output-contract-regression');

it('hydrates a raw schema x-php-class through the complete pipeline', function () {
    $result = contractRegressionOutput()
        ->with(
            messages: 'The issue is open.',
            responseModel: contractRegressionSchema(ContractRegressionDto::class),
        )
        ->get();

    expect($result)
        ->toBeInstanceOf(ContractRegressionDto::class)
        ->status->toBe('open');
})->group('structured-output-contract-regression');

it('keeps equivalent raw and parsed schemas on the same default target contract', function () {
    $jsonSchema = contractRegressionSchema(stdClass::class);
    $parsedSchema = (new JsonSchemaParser())->fromJsonSchema($jsonSchema);
    $builderSchema = SchemaBuilder::fromSchema($parsedSchema)->schema();
    $raw = makeAnyResponseModel($jsonSchema);
    $parsed = makeAnyResponseModel($parsedSchema);
    $builder = makeAnyResponseModel($builderSchema);

    expect($raw->outputFormat())->toEqual($parsed->outputFormat())
        ->and($parsed->outputFormat())->toEqual($builder->outputFormat())
        ->and($raw->outputFormat()->targetClass())->toBe(stdClass::class);
})->group('structured-output-contract-regression');

it('fails during preparation when schema class metadata is impossible', function () {
    $schema = contractRegressionSchema('ContractRegressionMissingOutputClass');

    expect(fn() => contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: $schema)
        ->get())
        ->toThrow(
            InvalidArgumentException::class,
            'Output class does not exist for schema default_schema: ContractRegressionMissingOutputClass',
        );
})->group('structured-output-contract-regression');

it('lets an explicit output target override impossible schema class metadata', function () {
    $result = contractRegressionOutput()
        ->with(
            messages: 'The issue is open.',
            responseModel: contractRegressionSchema('ContractRegressionMissingOutputClass'),
        )
        ->intoArray()
        ->get();

    expect($result)->toBe(['status' => 'open']);
})->group('structured-output-contract-regression');

it('honors an explicit stdClass target for a plain schema', function () {
    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->intoStdClass()
        ->get();

    expect($result)
        ->toBeInstanceOf(stdClass::class)
        ->status->toBe('open');
})->group('structured-output-contract-regression');

it('returns an explicit Structure without implicit scalar or array transformation', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('issue')
            ->option('status', ['open', 'closed'])
            ->schema(),
    );

    $result = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: $structure)
        ->get();

    expect($result)->toBeInstanceOf(Structure::class)
        ->and($result->toArray())->toBe(['status' => 'open']);
})->group('structured-output-contract-regression');

it('rejects invalid scalar enum values for every target kind', function () {
    $schema = contractRegressionSchema();
    $structure = Structure::fromSchema(
        SchemaBuilder::define('issue')
            ->option('status', ['open', 'closed'])
            ->schema(),
    );
    $requests = [
        contractRegressionOutput('{"status":"pending"}')
            ->with(messages: 'Invalid status.', responseModel: $schema),
        contractRegressionOutput('{"status":"pending"}')
            ->with(messages: 'Invalid status.', responseModel: $schema)
            ->intoStdClass(),
        contractRegressionOutput('{"status":"pending"}')
            ->with(messages: 'Invalid status.', responseModel: $schema)
            ->intoInstanceOf(ContractRegressionDto::class),
        contractRegressionOutput('{"status":"pending"}')
            ->with(messages: 'Invalid status.', responseModel: $schema)
            ->intoSelfDeserializing(new ContractRegressionSelfTarget('state:')),
        contractRegressionOutput('{"status":"pending"}')
            ->with(messages: 'Invalid status.', responseModel: $structure),
    ];

    foreach ($requests as $request) {
        try {
            $request->get();
            $this->fail('Invalid enum value should fail structured output generation.');
        } catch (StructuredOutputRecoveryException $error) {
            expect($error->getMessage())->toContain('enum/options');
        }
    }
})->group('structured-output-contract-regression');

it('keeps sync and completed stream parity for every final target kind', function (string $target) {
    $syncTransformer = new ContractRegressionIdentityTransformer();
    $syncRuntime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        outputMode: OutputMode::Json,
        transformer: $syncTransformer,
    );
    $sync = contractRegressionForTarget(new StructuredOutput($syncRuntime), $target)->get();

    $streamTransformer = new ContractRegressionIdentityTransformer();
    $streamRuntime = makeStructuredRuntime(
        driver: new FakeInferenceDriver(streamBatches: [[
            new PartialInferenceDelta(contentDelta: '{"status":"'),
            new PartialInferenceDelta(contentDelta: 'open"}'),
            new PartialInferenceDelta(finishReason: 'stop'),
        ]]),
        outputMode: OutputMode::Json,
        transformer: $streamTransformer,
    );
    $stream = contractRegressionForTarget(new StructuredOutput($streamRuntime), $target)
        ->withStreaming()
        ->stream()
        ->finalValue();

    expect(get_debug_type($stream))->toBe(get_debug_type($sync))
        ->and($syncTransformer->calls)->toBe(1)
        ->and($streamTransformer->calls)->toBe(1);

    match ($target) {
        'array' => expect($stream)->toBe($sync)->toBe(['status' => 'open']),
        'stdClass', 'class' => expect($stream->status)->toBe('open')->and($sync->status)->toBe('open'),
        'self' => expect($stream->status)->toBe('state:open')->and($sync->status)->toBe('state:open'),
        'structure' => expect($stream->toArray())->toBe(['status' => 'open'])
            ->and($sync->toArray())->toBe(['status' => 'open']),
    };
})->with([
    'array',
    'stdClass',
    'class',
    'self',
    'structure',
])->group('structured-output-contract-regression');

it('reports nested Structure hydration failure with its field path', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('wrapper')
            ->object('nested', ContractRegressionNestedDto::class)
            ->schema(),
    );

    expect(fn() => $structure->fromArray([
        'nested' => ['count' => 'not-an-integer'],
    ]))->toThrow(UnexpectedValueException::class, 'Failed to hydrate nested');
})->group('structured-output-contract-regression');

it('runs configured array transformation exactly once for get and completed stream', function () {
    $syncTransformer = new ContractRegressionCountingTransformer();
    $syncDriver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"status":"open"}'),
    ]);
    $syncRuntime = makeStructuredRuntime(
        driver: $syncDriver,
        outputMode: OutputMode::Json,
        transformer: $syncTransformer,
    );
    $sync = (new StructuredOutput($syncRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    $streamTransformer = new ContractRegressionCountingTransformer();
    $streamDriver = new FakeInferenceDriver(streamBatches: [[
        new PartialInferenceDelta(contentDelta: '{"status":"'),
        new PartialInferenceDelta(contentDelta: 'open"}'),
        new PartialInferenceDelta(finishReason: 'stop'),
    ]]);
    $streamRuntime = makeStructuredRuntime(
        driver: $streamDriver,
        outputMode: OutputMode::Json,
        transformer: $streamTransformer,
    );
    $stream = (new StructuredOutput($streamRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->withStreaming()
        ->stream()
        ->finalValue();

    expect($sync)->toBe(['status' => 'open', 'transformed' => true])
        ->and($stream)->toBe($sync)
        ->and($syncTransformer->calls)->toBe(1)
        ->and($streamTransformer->calls)->toBe(1);
})->group('structured-output-contract-regression');

it('validates completed streaming data before final transformation', function () {
    $transformer = new ContractRegressionCountingTransformer();
    $driver = new FakeInferenceDriver(streamBatches: [[
        new PartialInferenceDelta(contentDelta: '{"status":"pending"}'),
        new PartialInferenceDelta(finishReason: 'stop'),
    ]]);
    $runtime = makeStructuredRuntime(
        driver: $driver,
        outputMode: OutputMode::Json,
        maxRetries: 0,
        transformer: $transformer,
    );
    $request = (new StructuredOutput($runtime))
        ->with(messages: 'Invalid status.', responseModel: contractRegressionSchema())
        ->withStreaming();

    expect(fn() => $request->stream()->finalValue())
        ->toThrow(StructuredOutputRecoveryException::class, 'enum/options')
        ->and($transformer->calls)->toBe(0);
})->group('structured-output-contract-regression');

it('fails and retries instead of returning input after transformation failure', function () {
    $syncTransformer = new ContractRegressionFailOnceTransformer();
    $syncDriver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"status":"open"}'),
        new InferenceResponse(content: '{"status":"open"}'),
    ]);
    $syncRuntime = makeStructuredRuntime(
        driver: $syncDriver,
        outputMode: OutputMode::Json,
        maxRetries: 1,
        transformer: $syncTransformer,
    );
    $sync = (new StructuredOutput($syncRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    $streamTransformer = new ContractRegressionFailOnceTransformer();
    $batch = [
        new PartialInferenceDelta(contentDelta: '{"status":"open"}'),
        new PartialInferenceDelta(finishReason: 'stop'),
    ];
    $streamDriver = new FakeInferenceDriver(streamBatches: [$batch, $batch]);
    $streamRuntime = makeStructuredRuntime(
        driver: $streamDriver,
        outputMode: OutputMode::Json,
        maxRetries: 1,
        transformer: $streamTransformer,
    );
    $stream = (new StructuredOutput($streamRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->withStreaming()
        ->stream()
        ->finalValue();

    expect($sync)->toBe(['status' => 'open'])
        ->and($stream)->toBe($sync)
        ->and($syncTransformer->calls)->toBe(2)
        ->and($streamTransformer->calls)->toBe(2)
        ->and($syncDriver->responseCalls)->toBe(2)
        ->and($streamDriver->streamCalls)->toBe(2);
})->group('structured-output-contract-regression');

it('treats a null transformer result as documented identity success', function () {
    $events = new EventDispatcher();
    $successes = 0;
    $events->addListener(ResponseTransformed::class, function () use (&$successes): void {
        $successes++;
    });
    $transformer = new ContractRegressionNullTransformer();
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        events: $events,
        outputMode: OutputMode::Json,
        transformer: $transformer,
    );

    $result = (new StructuredOutput($runtime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    expect($result)->toBe(['status' => 'open'])
        ->and($transformer->calls)->toBe(1)
        ->and($successes)->toBe(1);
})->group('structured-output-contract-regression');

it('emits balanced success events for configured and self transformation', function () {
    $events = new EventDispatcher();
    $counts = ['attempt' => 0, 'success' => 0, 'failure' => 0];
    $events->addListener(ResponseTransformationAttempt::class, function () use (&$counts): void {
        $counts['attempt']++;
    });
    $events->addListener(ResponseTransformed::class, function () use (&$counts): void {
        $counts['success']++;
    });
    $events->addListener(ResponseTransformationFailed::class, function () use (&$counts): void {
        $counts['failure']++;
    });

    $configured = new ContractRegressionCountingTransformer();
    $configuredRuntime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        events: $events,
        outputMode: OutputMode::Json,
        transformer: $configured,
    );
    (new StructuredOutput($configuredRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    $counter = new ContractRegressionCallCounter();
    $selfRuntime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        events: $events,
        outputMode: OutputMode::Json,
    );
    $selfResult = (new StructuredOutput($selfRuntime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->intoObject(new ContractRegressionSelfTransformer($counter))
        ->get();

    expect($selfResult)->toBe(['status' => 'open', 'self_transformed' => true])
        ->and($configured->calls)->toBe(1)
        ->and($counter->calls)->toBe(1)
        ->and($counts)->toBe(['attempt' => 2, 'success' => 2, 'failure' => 0]);
})->group('structured-output-contract-regression');

it('preserves the distinct failure stage through retry telemetry', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseRetryScheduled::class, function (object $event) use (&$captured): void {
        $captured[] = $event->data;
    });
    $transformer = new ContractRegressionFailOnceTransformer();
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([
            new InferenceResponse(content: '{"status":"open"}'),
            new InferenceResponse(content: '{"status":"open"}'),
        ]),
        events: $events,
        outputMode: OutputMode::Json,
        maxRetries: 1,
        transformer: $transformer,
    );

    (new StructuredOutput($runtime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['stage'])->toBe('transformation')
        ->and($captured[0]['phase'])->toBe('response.retry_scheduled');
})->group('structured-output-contract-regression');

it('emits a result-neutral materialization event with the actual result type', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseMaterialized::class, function (object $event) use (&$captured): void {
        $captured[] = $event->data;
    });
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        events: $events,
        outputMode: OutputMode::Json,
    );

    (new StructuredOutput($runtime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->get();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['resultType'])->toBe('array')
        ->and($captured[0])->toHaveKeys(['requestId', 'executionId', 'attemptId', 'phaseId']);
})->group('structured-output-contract-regression');

// The removed StructuredOutputConfigBuilder merged single-mode prompt overrides into the
// defaults rather than replacing them. withModePromptClass() must keep doing that, or every
// former builder call site would silently lose the other modes' prompts.
it('merges rather than replaces modePromptClasses on a single-mode override', function () {
    $defaults = new StructuredOutputConfig(modePromptClasses: [
        OutputMode::Json->value => 'App\\Prompts\\DefaultJsonPrompt',
        OutputMode::Tools->value => 'App\\Prompts\\DefaultToolsPrompt',
    ]);
    $config = $defaults->withModePromptClass(OutputMode::Json, 'App\\Prompts\\OverriddenJsonPrompt');

    expect($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\OverriddenJsonPrompt')
        ->and($config->modePromptClass(OutputMode::Tools))->toBe('App\\Prompts\\DefaultToolsPrompt');
})->group('structured-output-contract-regression');

it('retains streamMaterializationInterval through StructuredOutputConfig', function () {
    $config = (new StructuredOutputConfig())->withStreamMaterializationInterval(7);
    $restored = StructuredOutputConfig::fromArray($config->toArray());

    expect($config->streamMaterializationInterval())->toBe(7)
        ->and($restored->streamMaterializationInterval())->toBe(7);
})->group('structured-output-contract-regression');

it('ignores removed legacy inline prompt keys when building the structured prompt', function () {
    $config = StructuredOutputConfig::fromArray([
        'outputMode' => OutputMode::Json,
        'modePrompts' => [OutputMode::Json->value => 'LEGACY INLINE PROMPT'],
    ]);
    $request = new StructuredOutputRequest(
        messages: Messages::fromString('Extract the issue status.'),
        requestedSchema: contractRegressionSchema(),
    );
    $execution = (new StructuredOutputExecutionBuilder(new EventDispatcher()))
        ->createWith(request: $request, config: $config);

    $defaultMessages = (new StructuredPromptRequestMaterializer())->toMessages($execution)->toArray();

    expect(json_encode($defaultMessages))->not->toContain('LEGACY INLINE PROMPT');
})->group('structured-output-contract-regression');

it('keeps raw toArray data distinct from the final structured array', function () {
    $transformer = new ContractRegressionCountingTransformer();
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"status":"open"}')]),
        outputMode: OutputMode::Json,
        transformer: $transformer,
    );
    $pending = (new StructuredOutput($runtime))
        ->with(messages: 'The issue is open.', responseModel: contractRegressionSchema())
        ->create();

    expect($pending->toArray())->toBe(['status' => 'open'])
        ->and($pending->getArray())->toBe(['status' => 'open', 'transformed' => true])
        ->and($transformer->calls)->toBe(1);
})->group('structured-output-contract-regression');

it('reports the concrete result type from typed getters', function () {
    $pending = contractRegressionOutput()
        ->with(messages: 'The issue is open.', responseModel: ContractRegressionDto::class)
        ->create();

    expect(fn() => $pending->getArray())
        ->toThrow(UnexpectedStructuredOutputTypeException::class, 'got ContractRegressionDto');
})->group('structured-output-contract-regression');

it('rejects request restoration that would fabricate a live target instance', function () {
    $arrayRequest = new StructuredOutputRequest(
        requestedSchema: contractRegressionSchema(),
        outputFormat: OutputFormat::array(),
    );
    $classRequest = new StructuredOutputRequest(
        requestedSchema: contractRegressionSchema(),
        outputFormat: OutputFormat::instanceOf(ContractRegressionDto::class),
    );
    $liveRequest = new StructuredOutputRequest(
        requestedSchema: contractRegressionSchema(),
        outputFormat: OutputFormat::selfDeserializing(new ContractRegressionSelfTarget('state:')),
    );

    expect(StructuredOutputRequest::fromArray($arrayRequest->toArray())->outputFormat())
        ->toEqual(OutputFormat::array())
        ->and(StructuredOutputRequest::fromArray($classRequest->toArray())->outputFormat())
        ->toEqual(OutputFormat::instanceOf(ContractRegressionDto::class))
        ->and(fn() => $liveRequest->toArray())
        ->toThrow(InvalidArgumentException::class, 'live instance state and cannot be serialized')
        ->and(fn() => StructuredOutputRequest::fromArray([
            'requestedSchema' => contractRegressionSchema(),
            'outputFormat' => [
                'type' => 'object',
                'class' => ContractRegressionSelfTarget::class,
            ],
        ]))
        ->toThrow(InvalidArgumentException::class, 'live instance state and cannot be restored');
})->group('structured-output-contract-regression');
