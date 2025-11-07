<?php declare(strict_types=1);

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

it('onSequenceUpdate emits last item for final sequence in partials engine', function () {
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
    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of('EvtPerson'))
        ->withOutputMode(OutputMode::Json)
        ->onSequenceUpdate(function (Sequence $seq) use (&$seen) {
            $last = $seq->last();
            $seen[] = is_object($last) ? ($last->name ?? null) : null;
        })
        ->create();

    $result = $pending->stream()->finalValue();

    expect($result)->toBeInstanceOf(Sequence::class);
    // Expect events for Jason, Jane, John and final Anna
    expect($seen)->toBe(['Jason', 'Jane', 'John', 'Anna']);
});
