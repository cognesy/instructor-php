<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\PendingInference;

it('uses a unique attempt id for each retry', function () {
    $events = new EventDispatcher();
    $attemptIds = [];

    $events->addListener(InferenceAttemptStarted::class, function (InferenceAttemptStarted $event) use (&$attemptIds): void {
        $attemptIds[] = $event->attemptId;
    });

    $driver = new class implements CanProcessInferenceRequest {
        private int $calls = 0;

        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            $this->calls++;
            if ($this->calls === 1) {
                throw new TimeoutException('timeout');
            }
            return new InferenceResponse(content: 'ok', finishReason: 'stop');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Retry')
        ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 2))
        ->create();
    $execution = InferenceExecution::fromRequest($request);

    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    $pending->response();

    expect($attemptIds)->toHaveCount(2);
    expect($attemptIds[0])->not->toBe($attemptIds[1]);
});
