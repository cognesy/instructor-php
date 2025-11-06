<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

// Simple DTOs
class SmokeUser { public int $age; public string $name; }
class SmokeItem { public string $title; }
enum SmokeStatus: string { case Active = 'active'; case Inactive = 'inactive'; }

// 1) Sync: generate response PHP object using provided response class
it('sync: deserializes object into provided class', function () {
    $json = '{"age":30,"name":"Alex"}';
    $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

    $obj = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseClass(SmokeUser::class)
        ->withOutputMode(OutputMode::Json)
        ->getObject();

    expect($obj)->toBeInstanceOf(SmokeUser::class);
    expect($obj->age)->toBe(30);
    expect($obj->name)->toBe('Alex');
});

// 2) Streaming: should receive a sequence of gradually completed PHP object
it('stream: yields partial updates of object progressively', function () {
    $stream = [
        new PartialInferenceResponse(contentDelta: '{"age":3'),
        new PartialInferenceResponse(contentDelta: '0,"name":"A'),
        new PartialInferenceResponse(contentDelta: 'lex"}'),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $stream ]);

    $partials = [];
    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseClass(SmokeUser::class)
        ->withOutputMode(OutputMode::Json)
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
    $v1 = (new StructuredOutput())
        ->withHttpClient(\Cognesy\Instructor\Tests\MockHttp::get([$intJson]))
        ->with(
            messages: [['role'=>'user','content'=>'age?']],
            responseModel: Scalar::integer('age'),
        )
        ->get();
    expect($v1)->toBeInt()->toBe(28);

    // String
    $v2 = (new StructuredOutput())
        ->withHttpClient(\Cognesy\Instructor\Tests\MockHttp::get([$strJson]))
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

    $b = (new StructuredOutput())
        ->withHttpClient(\Cognesy\Instructor\Tests\MockHttp::get([$boolJson]))
        ->with(messages: [['role'=>'user','content'=>'adult?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::boolean('isAdult'))
        ->get();
    expect($b)->toBeBool()->toBe(true);

    $f = (new StructuredOutput())
        ->withHttpClient(\Cognesy\Instructor\Tests\MockHttp::get([$floatJson]))
        ->with(messages: [['role'=>'user','content'=>'score?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::float('score'))
        ->get();
    expect($f)->toBeFloat()->toBe(99.5);

    $e = (new StructuredOutput())
        ->withHttpClient(\Cognesy\Instructor\Tests\MockHttp::get([$enumJson]))
        ->with(messages: [['role'=>'user','content'=>'status?']], responseModel: \Cognesy\Instructor\Extras\Scalar\Scalar::enum(SmokeStatus::class, 'status'))
        ->get();
    expect($e)->toBeInstanceOf(SmokeStatus::class)->toBe(SmokeStatus::Active);
});

// 4) Sequence::of(class) as response model - sync and stream
it('sequence: sync deserializes list of items', function () {
    $json = '{"list":[{"title":"A"},{"title":"B"}]}';
    $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

    /** @var \Cognesy\Instructor\Extras\Sequence\Sequence $seq */
    $seq = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of(SmokeItem::class))
        ->withOutputMode(OutputMode::Json)
        ->getObject();

    expect($seq)->toBeInstanceOf(Sequence::class);
    expect($seq->list)->toHaveCount(2);
    expect($seq->list[0])->toBeInstanceOf(SmokeItem::class);
    expect($seq->list[0]->title)->toBe('A');
    expect($seq->list[1]->title)->toBe('B');
});

it('sequence: streaming yields updates with complete items', function () {
    // Stream that completes first item, then second
    // Provide pre-valued partials to simulate progressive completion
    $s1 = new \Cognesy\Instructor\Extras\Sequence\Sequence(SmokeItem::class);
    $s1->list = [(function(){ $i=new SmokeItem(); $i->title='A'; return $i; })()];
    $s2 = new \Cognesy\Instructor\Extras\Sequence\Sequence(SmokeItem::class);
    $s2->list = [(function(){ $i=new SmokeItem(); $i->title='A'; return $i; })(), (function(){ $i=new SmokeItem(); $i->title='B'; return $i; })()];
    $sequenceStream = [
        (new PartialInferenceResponse(contentDelta: ''))->withValue($s1),
        (new PartialInferenceResponse(contentDelta: ''))->withValue($s2),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $sequenceStream ]);

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of(SmokeItem::class))
        ->withOutputMode(OutputMode::Json)
        ->create();

    $updates = [];
    foreach ($pending->stream()->sequence() as $seq) {
        $updates[] = $seq;
    }

    // We should observe completed snapshots as items get finalized
    expect(count($updates))->toBeGreaterThanOrEqual(2);
    $first = $updates[0];
    expect($first)->toBeInstanceOf(Sequence::class);
    expect($first->list)->toHaveCount(1);
    expect($first->list[0]->title)->toBe('A');
    $last = end($updates);
    expect($last->list)->toHaveCount(2);
    expect($last->list[1]->title)->toBe('B');
});

// 5) Tools mode: sync and streaming
class ToolUser { public int $age; }

it('tools mode: sync uses tool call args as JSON', function () {
    $tool = new ToolCall('extract', ['age' => 25]);
    $resp = new InferenceResponse(content: '', finishReason: 'stop', toolCalls: new ToolCalls($tool));
    $driver = new FakeInferenceDriver(responses: [ $resp ]);

    $obj = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseClass(ToolUser::class)
        ->withOutputMode(OutputMode::Tools)
        ->getObject();

    expect($obj)->toBeInstanceOf(ToolUser::class);
    expect($obj->age)->toBe(25);
});

it('tools mode: streaming assembles args from tool deltas', function () {
    $stream = [
        new PartialInferenceResponse(toolName: 'extract', toolArgs: '{"age":'),
        new PartialInferenceResponse(toolName: 'extract', toolArgs: '42}'),
    ];
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $stream ]);

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseClass(ToolUser::class)
        ->withOutputMode(OutputMode::Tools)
        ->create();

    $last = null;
    foreach ($pending->stream()->partials() as $p) { $last = $p; }
    expect($last)->toBeInstanceOf(ToolUser::class);
    expect($last->age)->toBe(42);
});
