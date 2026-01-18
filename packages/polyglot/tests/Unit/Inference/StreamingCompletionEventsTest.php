<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\PendingInference;

it('dispatches completion events once for streamed responses', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new class implements CanHandleInference {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return InferenceResponse::empty();
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            yield new PartialInferenceResponse(contentDelta: 'Hello', finishReason: 'stop');
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
        ->withMessages('Hi')
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    $pending->response();

    $attemptSucceeded = array_filter($captured, fn(object $event): bool => $event instanceof InferenceAttemptSucceeded);
    $usageReported = array_filter($captured, fn(object $event): bool => $event instanceof InferenceUsageReported);
    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect(count($attemptSucceeded))->toBe(1);
    expect(count($usageReported))->toBe(1);
    expect(count($completed))->toBe(1);
});
