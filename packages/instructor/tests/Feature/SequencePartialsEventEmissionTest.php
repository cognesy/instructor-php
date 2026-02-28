<?php declare(strict_types=1);

use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

class EvtPartialUser
{
    public int $count;
    public function __construct(int $count) { $this->count = $count; }
}

it('emits PartialResponseGenerated events while streaming partial updates', function () {
    $p1 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new EvtPartialUser(1));
    $p2 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new EvtPartialUser(2));
    $p3 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new EvtPartialUser(3));

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[ $p1, $p2, $p3 ]],
    );

    $seen = [];
    $stream = (new StructuredOutput(makeStructuredRuntime(driver: $driver)))
        ->withMessages('ignored')
        ->withResponseClass(EvtPartialUser::class)
        ->withOutputMode(OutputMode::Json)
        ->withStreaming()
        ->onEvent(PartialResponseGenerated::class, function (PartialResponseGenerated $event) use (&$seen): void {
            $partial = $event->partialResponse;
            if ($partial instanceof EvtPartialUser) {
                $seen[] = $partial->count;
            }
        })
        ->create()
        ->stream();

    foreach ($stream->responses() as $_) {}

    expect($seen)->toBe([1, 2, 3]);
});

it('emits SequenceUpdated events including final sequence item', function () {
    if (!class_exists('EvtPerson')) {
        eval('class EvtPerson { public string $name; public int $age; }');
    }

    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Jason","age":25}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"Jane","age":18}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"John","age":30}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"Anna","age":28}'),
        new PartialInferenceResponse(contentDelta: ']}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $seen = [];
    $pending = (new StructuredOutput(makeStructuredRuntime(driver: $driver)))
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of('EvtPerson'))
        ->withOutputMode(OutputMode::Json)
        ->onEvent(SequenceUpdated::class, function (SequenceUpdated $event) use (&$seen): void {
            $last = $event->sequence->last();
            $seen[] = $last->name ?? null;
        })
        ->create();

    $result = $pending->stream()->finalValue();

    expect($result)->toBeInstanceOf(Sequence::class);
    // Expect events for Jason, Jane, John and final Anna
    expect($seen)->toBe(['Jason', 'Jane', 'John', 'Anna']);
});
