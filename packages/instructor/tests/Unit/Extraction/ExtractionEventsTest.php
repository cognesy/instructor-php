<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Unit\Extraction;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyAttempted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategySucceeded;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Helper to create InferenceResponse from text content.
 */
function makeResponse(string $content): InferenceResponse
{
    return new InferenceResponse(content: $content);
}

describe('Extraction Events', function () {
    it('dispatches ExtractionStarted and ExtractionCompleted on success', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: $dispatcher,
        );

        $response = makeResponse('{"name":"John","age":30}');
        $result = $extractor->extract($response, OutputMode::Json);

        expect($result->isSuccess())->toBeTrue();

        $eventTypes = array_map(fn($e) => get_class($e), $events);

        expect($eventTypes)->toContain(ExtractionStarted::class);
        expect($eventTypes)->toContain(ExtractionStrategyAttempted::class);
        expect($eventTypes)->toContain(ExtractionStrategySucceeded::class);
        expect($eventTypes)->toContain(ExtractionCompleted::class);
    });

    it('dispatches ExtractionFailed when all strategies fail', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: $dispatcher,
        );

        $response = makeResponse('This is not JSON at all');
        $result = $extractor->extract($response, OutputMode::Json);

        expect($result->isFailure())->toBeTrue();

        $eventTypes = array_map(fn($e) => get_class($e), $events);

        expect($eventTypes)->toContain(ExtractionStarted::class);
        expect($eventTypes)->toContain(ExtractionStrategyAttempted::class);
        expect($eventTypes)->toContain(ExtractionStrategyFailed::class);
        expect($eventTypes)->toContain(ExtractionFailed::class);
        expect($eventTypes)->not->toContain(ExtractionCompleted::class);
    });

    it('dispatches events for each strategy attempted', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: ResponseExtractor::defaultExtractors(),
            events: $dispatcher,
        );

        // Content that will fail DirectJson but succeed with BracketMatching
        $response = makeResponse('Here is some JSON: {"name":"John"}');
        $result = $extractor->extract($response, OutputMode::Json);

        expect($result->isSuccess())->toBeTrue();

        $attemptedEvents = array_filter($events, fn($e) => $e instanceof ExtractionStrategyAttempted);
        expect(count($attemptedEvents))->toBeGreaterThanOrEqual(1);

        // At least one strategy failed (DirectJson)
        $failedEvents = array_filter($events, fn($e) => $e instanceof ExtractionStrategyFailed);
        expect(count($failedEvents))->toBeGreaterThanOrEqual(1);
    });

    it('includes strategy name in event data', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: $dispatcher,
        );

        $response = makeResponse('{"name":"John"}');
        $extractor->extract($response, OutputMode::Json);

        $attemptedEvent = array_filter($events, fn($e) => $e instanceof ExtractionStrategyAttempted);
        $attemptedEvent = array_values($attemptedEvent)[0];

        expect($attemptedEvent->data['strategy'])->toBe('direct');
    });

    it('includes content length in started event', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: $dispatcher,
        );

        $content = '{"name":"John"}';
        $response = makeResponse($content);
        $extractor->extract($response, OutputMode::Json);

        $startedEvent = array_filter($events, fn($e) => $e instanceof ExtractionStarted);
        $startedEvent = array_values($startedEvent)[0];

        expect($startedEvent->data['content_length'])->toBe(strlen($content));
    });

    it('includes error details in failed event', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: $dispatcher,
        );

        $response = makeResponse('not json');
        $extractor->extract($response, OutputMode::Json);

        $failedEvent = array_filter($events, fn($e) => $e instanceof ExtractionFailed);
        $failedEvent = array_values($failedEvent)[0];

        expect($failedEvent->data)->toHaveKey('strategies_tried');
        expect($failedEvent->data)->toHaveKey('errors');
        expect($failedEvent->data['strategies_tried'])->toContain('direct');
    });

    it('works without events dispatcher', function () {
        $extractor = new ResponseExtractor(
            extractors: [new DirectJsonExtractor()],
            events: null,
        );

        $response = makeResponse('{"name":"John"}');
        $result = $extractor->extract($response, OutputMode::Json);

        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap())->toBe(['name' => 'John']);
    });

    it('withEvents returns new instance with dispatcher', function () {
        $events = [];
        $dispatcher = createEventCollector($events);

        $extractor = new ResponseExtractor();
        $withEvents = $extractor->withEvents($dispatcher);

        expect($withEvents)->not->toBe($extractor);

        $response = makeResponse('{"name":"John"}');
        $withEvents->extract($response, OutputMode::Json);

        expect(count($events))->toBeGreaterThan(0);
    });
});

/**
 * Helper to create an event collector.
 */
function createEventCollector(array &$events): EventDispatcherInterface
{
    return new class($events) implements EventDispatcherInterface {
        public function __construct(private array &$events) {}

        public function dispatch(object $event): object
        {
            $this->events[] = $event;
            return $event;
        }
    };
}