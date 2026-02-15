<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\PendingInference;

/**
 * Regression: streaming retries must not reuse a consumed/stale InferenceStream.
 *
 * Before the fix, PendingInference cached the InferenceStream instance and
 * never cleared it between retry iterations. On the second attempt the
 * same (already-drained) stream was returned by stream(), producing a
 * null final response and throwing a RuntimeException.
 */
it('creates a fresh stream on each retry attempt when streaming', function () {
    $events = new EventDispatcher();

    $streamCallCount = 0;

    $driver = new class($streamCallCount) implements CanProcessInferenceRequest {
        private int $calls = 0;
        private int $streamCallsRef;

        public function __construct(private int &$streamCalls) {
            $this->streamCallsRef = &$streamCalls;
        }

        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            $this->calls++;
            $this->streamCalls++;

            if ($this->calls === 1) {
                throw new TimeoutException('stream timeout');
            }

            // Second attempt: return valid stream chunks
            yield new PartialInferenceResponse(contentDelta: 'Hello');
            yield new PartialInferenceResponse(contentDelta: ' world', finishReason: 'stop');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test retry')
        ->withStreaming(true)
        ->withRetryPolicy(new InferenceRetryPolicy(
            maxAttempts: 2,
            baseDelayMs: 0,
        ))
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    $response = $pending->response();

    expect($response->content())->toBe('Hello world');
    // The driver's makeStreamResponsesFor was called twice (first failed, second succeeded)
    expect($streamCallCount)->toBe(2);
});

/**
 * Regression: calling stream() then response() must not re-execute the request.
 *
 * Before the fix, PendingInference::response() did not check for an existing
 * cachedStream. It entered the retry loop, reset cachedStream to null, then
 * makeResponse() called stream()->final() which created a NEW InferenceStream
 * and hit the driver a second time. With the fix, response() delegates to the
 * existing stream when one is present.
 */
it('does not re-execute when response() is called after stream() and dispatches lifecycle events', function () {
    $events = new EventDispatcher();

    $streamCallCount = 0;
    $dispatchedEvents = [];

    $events->addListener(InferenceStarted::class, function () use (&$dispatchedEvents): void {
        $dispatchedEvents[] = 'InferenceStarted';
    });
    $events->addListener(InferenceAttemptSucceeded::class, function () use (&$dispatchedEvents): void {
        $dispatchedEvents[] = 'InferenceAttemptSucceeded';
    });
    $events->addListener(InferenceCompleted::class, function (InferenceCompleted $e) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = 'InferenceCompleted:' . ($e->isSuccess ? 'success' : 'failure');
    });

    $driver = new class($streamCallCount) implements CanProcessInferenceRequest {
        public function __construct(private int &$streamCalls) {}

        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            $this->streamCalls++;
            yield new PartialInferenceResponse(contentDelta: 'Hello');
            yield new PartialInferenceResponse(contentDelta: ' world', finishReason: 'stop');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test')
        ->withStreaming(true)
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    // First: consume the stream
    $stream = $pending->stream();
    $finalFromStream = $stream->final();
    expect($finalFromStream->content())->toBe('Hello world');
    expect($streamCallCount)->toBe(1);

    // Second: calling response() must reuse the stream, not re-execute
    $responseResult = $pending->response();
    expect($responseResult->content())->toBe('Hello world');
    expect($streamCallCount)->toBe(1); // still 1 — no second driver call

    // Lifecycle events must be dispatched even through stream-first path
    expect($dispatchedEvents)->toContain('InferenceStarted');
    expect($dispatchedEvents)->toContain('InferenceAttemptSucceeded');
    expect($dispatchedEvents)->toContain('InferenceCompleted:success');
});

/**
 * Regression: streaming length-recovery must get a fresh stream for the
 * continuation request, not reuse the stale stream that produced the
 * length-truncated response.
 */
it('creates a fresh stream for length recovery continuation', function () {
    $events = new EventDispatcher();

    $requestMessages = [];

    $driver = new class($requestMessages) implements CanProcessInferenceRequest {
        private int $calls = 0;

        public function __construct(private array &$capturedMessages) {}

        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            $this->calls++;
            $this->capturedMessages[] = $request->messages();

            if ($this->calls === 1) {
                // First attempt: return content but finish with length
                yield new PartialInferenceResponse(contentDelta: 'partial');
                yield new PartialInferenceResponse(contentDelta: ' answer', finishReason: 'length');
            } else {
                // Continuation: return the rest
                yield new PartialInferenceResponse(contentDelta: 'complete');
                yield new PartialInferenceResponse(contentDelta: ' response', finishReason: 'stop');
            }
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Tell me everything')
        ->withStreaming(true)
        ->withRetryPolicy(new InferenceRetryPolicy(
            maxAttempts: 1,
            baseDelayMs: 0,
            lengthRecovery: 'continue',
            lengthMaxAttempts: 1,
            lengthContinuePrompt: 'Continue.',
        ))
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    $response = $pending->response();

    // The continuation request got a fresh stream and succeeded
    expect($response->content())->toBe('complete response');
    // Two calls: original + length recovery continuation
    expect($requestMessages)->toHaveCount(2);
});

/**
 * Regression: partial usage from stream chunks must be reported in
 * InferenceAttemptFailed when the stream throws mid-consumption.
 *
 * Before the fix, handleAttemptFailure read partialUsage from
 * PendingInference::$execution which was never updated with the
 * stream's accumulated partials. The stream's internal execution
 * (accessible via cachedStream->execution()) held the real usage.
 */
it('reports partial usage in failure event when stream throws after emitting chunks', function () {
    $events = new EventDispatcher();

    $capturedFailure = null;
    $events->addListener(InferenceAttemptFailed::class, function (InferenceAttemptFailed $e) use (&$capturedFailure): void {
        $capturedFailure = $e;
    });

    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            // Emit a chunk with usage, then throw
            yield new PartialInferenceResponse(
                contentDelta: 'partial',
                usage: new Usage(inputTokens: 10, outputTokens: 5),
                usageIsCumulative: true,
            );
            throw new TimeoutException('connection lost mid-stream');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test')
        ->withStreaming(true)
        ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 1))
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    try {
        $pending->response();
    } catch (TimeoutException) {
        // expected
    }

    expect($capturedFailure)->not->toBeNull();
    expect($capturedFailure->partialUsage)->not->toBeNull();
    expect($capturedFailure->partialUsage->inputTokens)->toBe(10);
    expect($capturedFailure->partialUsage->outputTokens)->toBe(5);
});

/**
 * Regression: response() must be idempotent after a stream-first execution.
 *
 * Before the fix, the cachedStream check came before the existingResponse
 * check in response(). On the first call the stream-first branch ran,
 * dispatched lifecycle events, and stored the response via
 * withSuccessfulAttempt(). On a second call, the cachedStream branch ran
 * AGAIN, dispatching duplicate InferenceStarted / AttemptStarted /
 * AttemptSucceeded / InferenceCompleted events.
 *
 * The fix reorders so existingResponse is checked first. After the first
 * response() call, execution holds the response and subsequent calls
 * short-circuit via the early return.
 */
it('is idempotent: response() called twice after stream dispatches lifecycle events exactly once', function () {
    $events = new EventDispatcher();

    $eventCounts = [
        'InferenceStarted' => 0,
        'InferenceAttemptSucceeded' => 0,
        'InferenceCompleted' => 0,
    ];

    $events->addListener(InferenceStarted::class, function () use (&$eventCounts): void {
        $eventCounts['InferenceStarted']++;
    });
    $events->addListener(InferenceAttemptSucceeded::class, function (InferenceAttemptSucceeded $e) use (&$eventCounts): void {
        $eventCounts['InferenceAttemptSucceeded']++;
        expect($e->attemptNumber)->toBe(1);
    });
    $events->addListener(InferenceCompleted::class, function (InferenceCompleted $e) use (&$eventCounts): void {
        $eventCounts['InferenceCompleted']++;
        expect($e->isSuccess)->toBeTrue();
    });

    $streamCallCount = 0;

    $driver = new class($streamCallCount) implements CanProcessInferenceRequest {
        public function __construct(private int &$streamCalls) {}

        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            $this->streamCalls++;
            yield new PartialInferenceResponse(contentDelta: 'Hello');
            yield new PartialInferenceResponse(contentDelta: ' world', finishReason: 'stop');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test idempotency')
        ->withStreaming(true)
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    // Consume the stream
    $stream = $pending->stream();
    $stream->final();
    expect($streamCallCount)->toBe(1);

    // First response() — dispatches lifecycle events via stream-first branch
    $first = $pending->response();
    expect($first->content())->toBe('Hello world');

    // Second response() — must hit existingResponse early return, no new events
    $second = $pending->response();
    expect($second->content())->toBe('Hello world');
    expect($second)->toBe($first); // same instance

    // Driver never called again
    expect($streamCallCount)->toBe(1);

    // Each lifecycle event dispatched exactly once
    expect($eventCounts['InferenceStarted'])->toBe(1);
    expect($eventCounts['InferenceAttemptSucceeded'])->toBe(1);
    expect($eventCounts['InferenceCompleted'])->toBe(1);
});

/**
 * Regression: stream-first branch must detect failed finish reasons and
 * throw instead of marking the outcome as success.
 *
 * Before the fix, the cachedStream branch unconditionally called
 * withSuccessfulAttempt() and dispatched InferenceCompleted with
 * isSuccess: true, even when the stream's final response had a failure
 * finish reason (e.g. length, content_filter).
 */
it('throws and dispatches failure events when stream-first response has a failed finish reason', function () {
    $events = new EventDispatcher();

    $capturedCompleted = null;
    $capturedFailure = null;

    $events->addListener(InferenceCompleted::class, function (InferenceCompleted $e) use (&$capturedCompleted): void {
        $capturedCompleted = $e;
    });
    $events->addListener(InferenceAttemptFailed::class, function (InferenceAttemptFailed $e) use (&$capturedFailure): void {
        $capturedFailure = $e;
    });

    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            yield new PartialInferenceResponse(contentDelta: 'truncated');
            yield new PartialInferenceResponse(contentDelta: ' output', finishReason: 'length');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test failure detection')
        ->withStreaming(true)
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    // Consume the stream (finishes with 'length')
    $stream = $pending->stream();
    $stream->final();

    // response() must detect the failure and throw
    $thrown = false;
    try {
        $pending->response();
    } catch (\RuntimeException $e) {
        $thrown = true;
        expect($e->getMessage())->toContain('length');
    }

    expect($thrown)->toBeTrue();
    expect($capturedCompleted)->not->toBeNull();
    expect($capturedCompleted->isSuccess)->toBeFalse();
    expect($capturedFailure)->not->toBeNull();
    expect($capturedFailure->willRetry)->toBeFalse();
});

/**
 * Regression: failed response() must throw on every subsequent call.
 *
 * Before the fix, failure branches stored the failed response via
 * withFailedAttempt() then threw. On the second call,
 * $this->execution->response() returned the failed response and the
 * early return silently succeeded — returning a response with
 * finishReason=length instead of throwing.
 *
 * The fix stores the terminal error and re-throws it on subsequent calls.
 */
it('re-throws on repeated response() calls after non-streaming failure', function () {
    $events = new EventDispatcher();

    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'truncated', finishReason: 'length');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test failure idempotency')
        ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 1))
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    // First call throws
    $firstError = null;
    try {
        $pending->response();
    } catch (\RuntimeException $e) {
        $firstError = $e;
    }
    expect($firstError)->not->toBeNull();
    expect($firstError->getMessage())->toContain('length');

    // Second call must also throw (same error)
    $secondError = null;
    try {
        $pending->response();
    } catch (\RuntimeException $e) {
        $secondError = $e;
    }
    expect($secondError)->not->toBeNull();
    expect($secondError)->toBe($firstError);

    // get() must also throw
    $thirdError = null;
    try {
        $pending->get();
    } catch (\RuntimeException $e) {
        $thirdError = $e;
    }
    expect($thirdError)->toBe($firstError);
});

it('re-throws on repeated response() calls after stream-first failure', function () {
    $events = new EventDispatcher();

    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            throw new \LogicException('Should not be called in streaming mode');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            yield new PartialInferenceResponse(contentDelta: 'partial');
            yield new PartialInferenceResponse(contentDelta: ' content', finishReason: 'content_filter');
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Test stream failure idempotency')
        ->withStreaming(true)
        ->create();

    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    // Consume stream first
    $stream = $pending->stream();
    $stream->final();

    // First response() call should throw (content_filter)
    $firstError = null;
    try {
        $pending->response();
    } catch (\RuntimeException $e) {
        $firstError = $e;
    }
    expect($firstError)->not->toBeNull();
    expect($firstError->getMessage())->toContain('content_filter');

    // Second response() call must also throw (same error)
    $secondError = null;
    try {
        $pending->response();
    } catch (\RuntimeException $e) {
        $secondError = $e;
    }
    expect($secondError)->toBe($firstError);
});
