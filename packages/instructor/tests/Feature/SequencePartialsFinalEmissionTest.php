<?php declare(strict_types=1);

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

it('partials engine emits all completed sequence items including final one', function () {
    if (!class_exists('SeqPerson')) {
        eval('class SeqPerson { public string $name; public int $age; }');
    }

    // Stream JSON content deltas forming a 4-item sequence
    $chunks = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", '{"list":[{"name":"Jason","age":25}')),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ',{"name":"Jane","age":18}')),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ',{"name":"John","age":30}')),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ',{"name":"Anna","age":28}')),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ']}'), finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)))
        ->withMessages('ignored')
        ->withResponseObject(Sequence::of('SeqPerson'))
        ->create();

    $items = iterator_to_array($pending->stream()->sequence());

    // We expect 4 individual completed items
    expect(count($items))->toBe(4);
    expect($items[0]->name)->toBe('Jason');
    expect($items[1]->name)->toBe('Jane');
    expect($items[2]->name)->toBe('John');
    expect($items[3]->name)->toBe('Anna');
});
