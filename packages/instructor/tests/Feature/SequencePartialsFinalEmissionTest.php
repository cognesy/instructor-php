<?php declare(strict_types=1);

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;

it('partials engine emits all completed sequence items including final one', function () {
    if (!class_exists('SeqPerson')) {
        eval('class SeqPerson { public string $name; public int $age; }');
    }

    // Stream JSON content deltas forming a 4-item sequence
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Jason","age":25}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"Jane","age":18}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"John","age":30}'),
        new PartialInferenceResponse(contentDelta: ',{"name":"Anna","age":28}'),
        new PartialInferenceResponse(contentDelta: ']}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceRequestDriver(responses: [], streamBatches: [ $chunks ]);

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of('SeqPerson'))
        ->withOutputMode(OutputMode::Json)
        ->create();

    $updates = iterator_to_array($pending->stream()->sequence());

    // We expect 4 snapshots: after 1st, 2nd, 3rd, and final (4th)
    expect(count($updates))->toBe(4);
    expect($updates[0]->count())->toBe(1);
    expect($updates[1]->count())->toBe(2);
    expect($updates[2]->count())->toBe(3);
    expect($updates[3]->count())->toBe(4);
});

