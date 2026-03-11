<?php declare(strict_types=1);

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

// Simple DTOs
class SmokeUser { public int $age; public string $name; }
class SmokeItem { public string $title; }
enum SmokeStatus: string { case Active = 'active'; case Inactive = 'inactive'; }

// 1) Sync: generate response PHP object using provided response class
it('sync: deserializes object into provided class', function () {
    $json = '{"age":30,"name":"Alex"}';
    $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

    $obj = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('ignored')
        ->withResponseClass(SmokeUser::class)
        ->getObject();

    expect($obj)->toBeInstanceOf(SmokeUser::class);
    expect($obj->age)->toBe(30);
    expect($obj->name)->toBe('Alex');
});

// 2) Streaming: should receive a sequence of gradually completed PHP object
it('stream: yields partial updates of object progressively', function () {
    $stream = [
        new PartialInferenceDelta(contentDelta: '{"age":3'),
        new PartialInferenceDelta(contentDelta: '0,"name":"A'),
        new PartialInferenceDelta(contentDelta: 'lex"}'),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $stream ]);

    $partials = [];
    $pending = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('ignored')
        ->withResponseClass(SmokeUser::class)
        ->create();

    // Consume stream to drive partials via API
    foreach ($pending->stream()->partials() as $p) { $partials[] = $p; }

    expect(count($partials))->toBeGreaterThan(0);
    $last = end($partials);
    expect($last)->toBeInstanceOf(SmokeUser::class);
    expect($last->age)->toBe(30);
    expect($last->name)->toBe('Alex');
});

// 3) Scalars: integer and string with correct deserialization
it('scalars: integer and string deserialize correctly', function () {
    // Using MockHttp-like behavior would be fine, but we can also drive via driver
    $intJson = '{"age":28}';
    $strJson = '{"firstName":"Jamie"}';

    // Integer
    $v1 = (new StructuredOutput(StructuredOutputRuntime::fromDefaults(
        httpClient: \Cognesy\Instructor\Tests\MockHttp::get([$intJson]),
    )))
        ->with(
            messages: [['role'=>'user','content'=>'age?']],
            responseModel: Scalar::integer('age'),
        )
        ->get();
    expect($v1)->toBeInt()->toBe(28);

    // String
    $v2 = (new StructuredOutput(StructuredOutputRuntime::fromDefaults(
        httpClient: \Cognesy\Instructor\Tests\MockHttp::get([$strJson]),
    )))
        ->with(
            messages: [['role'=>'user','content'=>'name?']],
            responseModel: Scalar::string('firstName'),
        )
        ->get();
    expect($v2)->toBeString()->toBe('Jamie');
});

it('scalars: boolean, float and enum deserialize correctly', function () {
    $boolJson = '{"isAdult":true}';
    $floatJson = '{"score":99.5}';
    $enumJson = '{"status":"active"}';

    $b = (new StructuredOutput(StructuredOutputRuntime::fromDefaults(
        httpClient: \Cognesy\Instructor\Tests\MockHttp::get([$boolJson]),
    )))
        ->with(messages: [['role'=>'user','content'=>'adult?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::boolean('isAdult'))
        ->get();
    expect($b)->toBeBool()->toBe(true);

    $f = (new StructuredOutput(StructuredOutputRuntime::fromDefaults(
        httpClient: \Cognesy\Instructor\Tests\MockHttp::get([$floatJson]),
    )))
        ->with(messages: [['role'=>'user','content'=>'score?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::float('score'))
        ->get();
    expect($f)->toBeFloat()->toBe(99.5);

    $e = (new StructuredOutput(StructuredOutputRuntime::fromDefaults(
        httpClient: \Cognesy\Instructor\Tests\MockHttp::get([$enumJson]),
    )))
        ->with(messages: [['role'=>'user','content'=>'status?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::enum(SmokeStatus::class, 'status'))
        ->get();
    expect($e)->toBeInstanceOf(SmokeStatus::class)->toBe(SmokeStatus::Active);
});

// 4) Sequence::of(class) as response model - sync and stream
it('sequence: sync deserializes list of items', function () {
    $json = '{"list":[{"title":"A"},{"title":"B"}]}';
    $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

    /** @var \Cognesy\Instructor\Extras\Sequence\Sequence $seq */
    $seq = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of(SmokeItem::class))
        ->getObject();

    expect($seq)->toBeInstanceOf(Sequence::class);
    expect($seq->all())->toHaveCount(2);
    expect($seq->get(0))->toBeInstanceOf(SmokeItem::class);
    expect($seq->get(0)->title)->toBe('A');
    expect($seq->get(1)->title)->toBe('B');
});

it('sequence: streaming yields updates with complete items', function () {
    // Stream that completes first item, then second
    // Provide pre-valued partials to simulate progressive completion
    $s1 = new \Cognesy\Instructor\Extras\Sequence\Sequence(SmokeItem::class);
    $s1->push((function(){ $i=new SmokeItem(); $i->title='A'; return $i; })());
    $s2 = new \Cognesy\Instructor\Extras\Sequence\Sequence(SmokeItem::class);
    $s2->push((function(){ $i=new SmokeItem(); $i->title='A'; return $i; })());
    $s2->push((function(){ $i=new SmokeItem(); $i->title='B'; return $i; })());
    $sequenceStream = [
        new PartialInferenceDelta(value: $s1),
        new PartialInferenceDelta(value: $s2),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $sequenceStream ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of(SmokeItem::class))
        ->create();

    $items = [];
    foreach ($pending->stream()->sequence() as $item) {
        $items[] = $item;
    }

    // We should get individual completed items
    expect(count($items))->toBeGreaterThanOrEqual(2);
    expect($items[0])->toBeInstanceOf(SmokeItem::class);
    expect($items[0]->title)->toBe('A');
    expect($items[1]->title)->toBe('B');
});

// 5) Tools mode: sync and streaming
class ToolUser { public int $age; }
class RuntimeFactoryUser { public int $age; public string $name; }

it('tools mode: sync uses tool call args as JSON', function () {
    $tool = new ToolCall('extract', ['age' => 25]);
    $resp = new InferenceResponse(content: '', finishReason: 'stop', toolCalls: new ToolCalls($tool));
    $driver = new FakeInferenceDriver(responses: [ $resp ]);

    $obj = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Tools)))
        ->withMessages('ignored')
        ->withResponseClass(ToolUser::class)
        ->getObject();

    expect($obj)->toBeInstanceOf(ToolUser::class);
    expect($obj->age)->toBe(25);
});

it('tools mode: streaming assembles args from tool deltas', function () {
    $stream = [
        new PartialInferenceDelta(toolName: 'extract', toolArgs: '{"age":'),
        new PartialInferenceDelta(toolArgs: '42}'),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $stream ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Tools)))
        ->withMessages('ignored')
        ->withResponseClass(ToolUser::class)
        ->create();

    $last = null;
    foreach ($pending->stream()->partials() as $p) { $last = $p; }
    expect($last)->toBeInstanceOf(ToolUser::class);
    expect($last->age)->toBe(42);
});

it('supports structured output runtime static factories', function () {
    $http = \Cognesy\Instructor\Tests\MockHttp::get([
        '{"name":"FromConfig","age":41}',
        '{"name":"FromResolver","age":42}',
        '{"name":"FromProvider","age":43}',
        '{"name":"FromDsn","age":44}',
        '{"name":"FromDriverConfig","age":45}',
    ], provider: 'openai');

    $provider = LLMProvider::fromLLMConfig(new LLMConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
    ));
    $llmConfig = $provider->resolveConfig();
    $structuredConfig = (new StructuredOutputConfig())->withOutputMode(OutputMode::Tools);
    $request = new StructuredOutputRequest(
        messages: \Cognesy\Messages\Messages::fromString('extract'),
        requestedSchema: RuntimeFactoryUser::class,
    );

    $fromConfig = StructuredOutputRuntime::fromConfig(
        config: $llmConfig,
        httpClient: $http,
        structuredConfig: $structuredConfig,
    )->create($request)->get();
    expect($fromConfig->name)->toBe('FromConfig');
    expect($fromConfig->age)->toBe(41);

    $fromProvider = StructuredOutputRuntime::fromProvider(
        provider: $provider,
        httpClient: $http,
        structuredConfig: $structuredConfig,
    )->create($request)->get();
    expect($fromProvider->name)->toBe('FromResolver');
    expect($fromProvider->age)->toBe(42);

    $fromDsnConfig = StructuredOutputRuntime::fromConfig(
        config: LLMConfig::fromDsn('driver=openai,apiUrl=https://api.openai.com/v1,apiKey=test,endpoint=/chat/completions,model=gpt-4o-mini'),
        httpClient: $http,
        structuredConfig: $structuredConfig,
    )->create($request)->get();
    expect($fromDsnConfig->name)->toBe('FromProvider');
    expect($fromDsnConfig->age)->toBe(43);

    $fromDefaults = StructuredOutputRuntime::fromDefaults(
        httpClient: $http,
        structuredConfig: $structuredConfig,
        llmConfig: $llmConfig,
    )->create($request)->get();
    expect($fromDefaults->name)->toBe('FromDsn');
    expect($fromDefaults->age)->toBe(44);

    $fromDriverConfig = StructuredOutputRuntime::fromConfig(
        config: LLMConfig::fromArray(['driver' => 'openai']),
        httpClient: $http,
        structuredConfig: $structuredConfig,
    )->create($request)->get();
    expect($fromDriverConfig->name)->toBe('FromDriverConfig');
    expect($fromDriverConfig->age)->toBe(45);
});
