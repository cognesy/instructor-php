<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\PendingInference;

it('uses a unique attempt id for each retry', function () {
    $events = new EventDispatcher();
    $attemptIds = [];

    $events->addListener(InferenceAttemptStarted::class, function (InferenceAttemptStarted $event) use (&$attemptIds): void {
        $attemptIds[] = $event->attemptId;
    });

    $driver = new class implements CanHandleInference {
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

        public function toHttpRequest(InferenceRequest $request): HttpRequest {
            return new HttpRequest(url: 'http://example.test', method: 'POST', headers: [], body: '', options: []);
        }

        public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse {
            return InferenceResponse::empty();
        }

        public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $request = (new InferenceRequestBuilder())
        ->withMessages('Retry')
        ->withOptions([
            'retryPolicy' => [
                'maxAttempts' => 2,
            ],
        ])
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
