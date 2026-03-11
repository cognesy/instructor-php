<?php declare(strict_types=1);

use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Tests\Instructor\Support\TestEventDispatcher;

require_once __DIR__ . '/../Support/TestEventDispatcher.php';

// Minimal stub to feed a predefined sequence of emissions
class FakeEmitterForSequence implements CanEmitStreamingUpdates {
    private \Generator $gen;
    private bool $started = false;
    private StructuredOutputExecution $execution;

    public function __construct(\Generator $gen, StructuredOutputExecution $execution) {
        $this->gen = $gen;
        $this->execution = $execution;
    }

    public function hasNextEmission(): bool {
        if (!$this->started) {
            return true;
        }
        return $this->gen->valid();
    }

    public function nextEmission(): ?StructuredOutputResponse {
        if (!$this->started) {
            $this->started = true;
        }
        if (!$this->gen->valid()) {
            return null;
        }
        $current = $this->gen->current();
        $this->gen->next();
        return $current;
    }

    public function execution(): StructuredOutputExecution {
        return $this->execution;
    }
}

it('yields individual completed items from sequence and dispatches events', function () {
    $dispatcher = new TestEventDispatcher();

    $seq = new Sequence();

    $generator = (function () use ($seq) {
        // first partial: 1 item
        $seq->push(['x' => 1]);
        yield StructuredOutputResponse::partial($seq, new InferenceResponse(isPartial: true));
        // second partial: same count (no new item), should not yield via sequence()
        yield StructuredOutputResponse::partial($seq, new InferenceResponse(isPartial: true));
        // third partial: new item (2nd)
        $seq->push(['x' => 2]);
        yield StructuredOutputResponse::partial($seq, new InferenceResponse(isPartial: true));
    })();

    $request = new Cognesy\Instructor\Data\StructuredOutputRequest(messages: Messages::fromString('dummy'), requestedSchema: []);
    $initial = new StructuredOutputExecution(request: $request, status: ExecutionStatus::Pending);

    $emitter = new FakeEmitterForSequence($generator, $initial);
    $stream = new StructuredOutputStream($initial, $emitter, $dispatcher);

    $items = iterator_to_array($stream->sequence());

    // sequence() yields individual completed items
    expect(count($items))->toBe(2);
    expect($items[0])->toBe(['x' => 1]);
    expect($items[1])->toBe(['x' => 2]);

    // Events dispatched: StructuredOutputStarted (constructor) + multiple StructuredOutputResponseUpdated (per-item)
    $types = array_map(fn($e) => get_class($e), $dispatcher->events);
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputStarted')))->not()->toBeEmpty();
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseUpdated')))->not()->toBeEmpty();
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseGenerated')))->toBeEmpty();
});

it('skips partial responses without parsed values before sequence items appear', function () {
    $dispatcher = new TestEventDispatcher();

    $generator = (function () {
        yield StructuredOutputResponse::partial(null, new InferenceResponse(isPartial: true));

        $sequence = new Sequence();
        $sequence->push(['x' => 1]);
        yield StructuredOutputResponse::partial($sequence, new InferenceResponse(isPartial: true));
    })();

    $request = new Cognesy\Instructor\Data\StructuredOutputRequest(messages: Messages::fromString('dummy'), requestedSchema: []);
    $initial = new StructuredOutputExecution(request: $request, status: ExecutionStatus::Pending);

    $stream = new StructuredOutputStream(
        $initial,
        new FakeEmitterForSequence($generator, $initial),
        $dispatcher,
    );

    $items = iterator_to_array($stream->sequence(), false);

    // The single item is finalized (held back then released)
    expect($items)->toHaveCount(1);
    expect($items[0])->toBe(['x' => 1]);
});
