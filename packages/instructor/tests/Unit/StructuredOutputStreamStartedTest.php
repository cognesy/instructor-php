<?php declare(strict_types=1);

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Tests\Instructor\Support\TestEventDispatcher;

require_once __DIR__ . '/../Support/TestEventDispatcher.php';

class SingleUpdateAttemptHandler implements CanHandleStructuredOutputAttempts
{
    private bool $done = false;

    public function __construct(
        private StructuredOutputExecution $update,
    ) {}

    public function hasNext(StructuredOutputExecution $execution): bool {
        return !$this->done;
    }

    public function nextUpdate(StructuredOutputExecution $execution): StructuredOutputExecution {
        $this->done = true;
        return $this->update;
    }
}

it('dispatches StructuredOutputStarted once across multiple stream reads', function () {
    $dispatcher = new TestEventDispatcher();

    $response = (new InferenceResponse())->withValue('ok');
    $inferenceExecution = InferenceExecution::empty()->withSuccessfulAttempt($response);
    $attempt = new StructuredOutputAttempt(inferenceExecution: $inferenceExecution);
    $attempts = StructuredOutputAttemptList::of($attempt);
    $request = new StructuredOutputRequest(messages: 'dummy', requestedSchema: []);

    $execution = new StructuredOutputExecution(
        request: $request,
        attempts: $attempts,
        currentAttempt: $attempt,
        isFinalized: false,
    );

    $handler = new SingleUpdateAttemptHandler($execution);
    $stream = new StructuredOutputStream($execution, $handler, $dispatcher);

    $stream->finalResponse();
    $stream->finalResponse();

    $startedEvents = array_filter(
        $dispatcher->events,
        fn (object $event): bool => $event instanceof StructuredOutputStarted
    );

    expect($startedEvents)->toHaveCount(1);
});

it('does not emit additional start events when reading the raw iterator', function () {
    $dispatcher = new TestEventDispatcher();

    $response = (new InferenceResponse())->withValue('ok');
    $inferenceExecution = InferenceExecution::empty()->withSuccessfulAttempt($response);
    $attempt = new StructuredOutputAttempt(inferenceExecution: $inferenceExecution);
    $attempts = StructuredOutputAttemptList::of($attempt);
    $request = new StructuredOutputRequest(messages: 'dummy', requestedSchema: []);

    $execution = new StructuredOutputExecution(
        request: $request,
        attempts: $attempts,
        currentAttempt: $attempt,
        isFinalized: false,
    );

    $handler = new SingleUpdateAttemptHandler($execution);
    $stream = new StructuredOutputStream($execution, $handler, $dispatcher);

    iterator_to_array($stream->getIterator());

    $startedEvents = array_filter(
        $dispatcher->events,
        fn (object $event): bool => $event instanceof StructuredOutputStarted
    );

    expect($startedEvents)->toHaveCount(1);
});
