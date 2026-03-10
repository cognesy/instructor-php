<?php declare(strict_types=1);

use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Tests\Instructor\Support\TestEventDispatcher;

require_once __DIR__ . '/../Support/TestEventDispatcher.php';

class SingleUpdateEmitter implements CanEmitStreamingUpdates
{
    private bool $done = false;

    public function __construct(
        private StructuredOutputExecution $execution,
        private StructuredOutputResponse $emission,
    ) {}

    public function hasNextEmission(): bool {
        return !$this->done;
    }

    public function nextEmission(): ?StructuredOutputResponse {
        $this->done = true;
        return $this->emission;
    }

    public function execution(): StructuredOutputExecution {
        return $this->execution;
    }
}

it('dispatches StructuredOutputStarted once across multiple stream reads', function () {
    $dispatcher = new TestEventDispatcher();

    $response = new InferenceResponse(content: 'ok');
    $request = new StructuredOutputRequest(messages: Messages::fromString('dummy'), requestedSchema: []);

    $execution = (new StructuredOutputExecution(request: $request))
        ->withStartedAttempt()
        ->withSuccessfulAttempt($response, 'ok');

    $emitter = new SingleUpdateEmitter(
        $execution,
        StructuredOutputResponse::final(value: 'ok', inferenceResponse: $response),
    );
    $stream = new StructuredOutputStream($execution, $emitter, $dispatcher);

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

    $response = new InferenceResponse(content: 'ok');
    $request = new StructuredOutputRequest(messages: Messages::fromString('dummy'), requestedSchema: []);

    $execution = (new StructuredOutputExecution(request: $request))
        ->withStartedAttempt()
        ->withSuccessfulAttempt($response, 'ok');

    $emitter = new SingleUpdateEmitter(
        $execution,
        StructuredOutputResponse::final(value: 'ok', inferenceResponse: $response),
    );
    $stream = new StructuredOutputStream($execution, $emitter, $dispatcher);

    iterator_to_array($stream->getIterator());

    $startedEvents = array_filter(
        $dispatcher->events,
        fn (object $event): bool => $event instanceof StructuredOutputStarted
    );

    expect($startedEvents)->toHaveCount(1);
});
