<?php declare(strict_types=1);

use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

class EvtPartialUser
{
    public int $count;
    public function __construct(int $count) { $this->count = $count; }
}

it('emits PartialResponseGenerated events while streaming partial updates', function () {
    $p1 = new PartialInferenceDelta(value: new EvtPartialUser(1));
    $p2 = new PartialInferenceDelta(value: new EvtPartialUser(2));
    $p3 = new PartialInferenceDelta(value: new EvtPartialUser(3));

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[ $p1, $p2, $p3 ]],
    );

    $seen = [];
    $payloads = [];
    $runtime = makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)
        ->onEvent(PartialResponseGenerated::class, function (PartialResponseGenerated $event) use (&$seen, &$payloads): void {
            $partial = $event->partialResponse;
            if ($partial instanceof EvtPartialUser) {
                $seen[] = $partial->count;
            }
            $payloads[] = $event->data;
        });
    $stream = (new StructuredOutput($runtime))
        ->withMessages('ignored')
        ->withResponseClass(EvtPartialUser::class)
        ->withStreaming()
        ->create()
        ->stream();

    foreach ($stream->responses() as $_) {}

    expect($seen)->toBe([1, 2, 3]);
    expect($payloads)->toBe([
        [
            'valueType' => EvtPartialUser::class,
            'value' => ['count' => 1],
        ],
        [
            'valueType' => EvtPartialUser::class,
            'value' => ['count' => 2],
        ],
        [
            'valueType' => EvtPartialUser::class,
            'value' => ['count' => 3],
        ],
    ]);
});

it('emits SequenceUpdated events including final sequence item', function () {
    if (!class_exists('EvtPerson')) {
        eval('class EvtPerson { public string $name; public int $age; }');
    }

    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Jason","age":25}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Jane","age":18}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"John","age":30}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Anna","age":28}'),
        new PartialInferenceDelta(contentDelta: ']}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $seen = [];
    $runtime = makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)
        ->onEvent(SequenceUpdated::class, function (SequenceUpdated $event) use (&$seen): void {
            $item = $event->completedItem();
            $seen[] = $item->name ?? null;
        });
    $pending = (new StructuredOutput($runtime))
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of('EvtPerson'))
        ->create();

    $result = $pending->stream()->finalValue();

    expect($result)->toBeInstanceOf(Sequence::class);
    // Expect events for Jason, Jane, John and final Anna
    expect($seen)->toBe(['Jason', 'Jane', 'John', 'Anna']);
});
