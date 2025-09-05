<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Tests\Instructor\Support\TestEventDispatcher;

require_once __DIR__ . '/../Support/TestEventDispatcher.php';

it('yields sequence updates only when new items complete and dispatches events', function () {
    $dispatcher = new TestEventDispatcher();

    $seq = new Sequence();

    $generator = (function () use ($seq) {
        // first partial: 1 item
        $seq->push(['x' => 1]);
        yield (new PartialInferenceResponse())->withValue($seq);
        // second partial: same count (no new item), should not yield via sequence()
        yield (new PartialInferenceResponse())->withValue($seq);
        // third partial: new item (2nd)
        $seq->push(['x' => 2]);
        yield (new PartialInferenceResponse())->withValue($seq);
    })();

    $stream = new StructuredOutputStream($generator, $dispatcher);

    $updates = iterator_to_array($stream->sequence());
    // Expect: yields previous sequence when new item completes, then final sequence at end
    expect(count($updates))->toBe(2);
    expect($updates[0])->toBeInstanceOf(Sequence::class);
    expect($updates[1])->toBeInstanceOf(Sequence::class);
    expect($updates[0]->count())->toBe(2);
    expect($updates[1]->count())->toBe(2);

    // Events dispatched: multiple StructuredOutputResponseUpdated and 1 StructuredOutputResponseGenerated
    $types = array_map(fn($e) => get_class($e), $dispatcher->events);
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseUpdated')))->not()->toBeEmpty();
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseGenerated')))->not()->toBeEmpty();
});
