<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Collections\StructuredOutputAttempts;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Core\RequestHandler;
use Tests\Instructor\Support\TestEventDispatcher;

require_once __DIR__ . '/../Support/TestEventDispatcher.php';

// Minimal stub to feed a predefined generator of execution updates
class FakeRequestHandlerForSequence extends RequestHandler {
    private \Generator $gen;
    public function __construct(\Generator $gen) { $this->gen = $gen; }
    public function streamUpdatesFor(StructuredOutputExecution $execution): \Generator { yield from $this->gen; }
}

it('yields sequence updates only when new items complete and dispatches events', function () {
    $dispatcher = new TestEventDispatcher();

    $seq = new Sequence();

    $generator = (function () use ($seq) {
        // helper to wrap a value into a StructuredOutputExecution carrying an InferenceResponse
        $wrap = function($value) {
            $response = (new InferenceResponse())->withValue($value);
            $infExec = InferenceExecution::empty()->withNewResponse($response);
            $attempt = new \Cognesy\Instructor\Data\StructuredOutputAttempt(
                inferenceExecution: $infExec,
                isFinalized: false,
            );
            $attempts = StructuredOutputAttempts::of($attempt);
            $request = new Cognesy\Instructor\Data\StructuredOutputRequest(messages: 'dummy', requestedSchema: []);
            return new StructuredOutputExecution(
                request: $request,
                attempts: $attempts,
                currentAttempt: $attempt,
                isFinalized: false,
            );
        };

        // first partial: 1 item
        $seq->push(['x' => 1]);
        yield $wrap($seq);
        // second partial: same count (no new item), should not yield via sequence()
        yield $wrap($seq);
        // third partial: new item (2nd)
        $seq->push(['x' => 2]);
        yield $wrap($seq);
    })();

    // Provide initial execution and a request handler stub that yields our updates
    $initial = (function () {
        $seq = new Sequence();
        $response = (new InferenceResponse())->withValue($seq);
        $infExec = InferenceExecution::empty()->withNewResponse($response);
        $attempt = new \Cognesy\Instructor\Data\StructuredOutputAttempt(
            inferenceExecution: $infExec,
            isFinalized: false,
        );
        $attempts = StructuredOutputAttempts::of($attempt);
        $request = new Cognesy\Instructor\Data\StructuredOutputRequest(messages: 'dummy', requestedSchema: []);
        return new StructuredOutputExecution(
            request: $request,
            attempts: $attempts,
            currentAttempt: $attempt,
            isFinalized: false,
        );
    })();

    $handler = new FakeRequestHandlerForSequence($generator);
    $stream = new StructuredOutputStream($initial, $handler, $dispatcher);

    $updates = iterator_to_array($stream->sequence());
    // Expect: yields previous sequence when new item completes, then final sequence at end
    expect(count($updates))->toBe(2);
    expect($updates[0])->toBeInstanceOf(Sequence::class);
    expect($updates[1])->toBeInstanceOf(Sequence::class);
    expect($updates[0]->count())->toBe(1);
    expect($updates[1]->count())->toBe(2);

    // Events dispatched: multiple StructuredOutputResponseUpdated and 1 StructuredOutputResponseGenerated
    $types = array_map(fn($e) => get_class($e), $dispatcher->events);
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseUpdated')))->not()->toBeEmpty();
    expect(array_filter($types, fn($t) => str_contains($t, 'StructuredOutputResponseGenerated')))->not()->toBeEmpty();
});
