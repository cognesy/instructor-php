<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials;

use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Executors\Partials\ResponseAggregation\AggregationState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Iterator;
use IteratorAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Policy-aware event dispatcher with error handling and batching.
 * Decorates a stream to dispatch events as values flow through.
 *
 * @implements IteratorAggregate<int, mixed>
 */
final class EventDispatchingStream implements IteratorAggregate
{
    public function __construct(
        private iterable $inner,
        private EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->inner as $value) {
            // Immediate, strict dispatch
            $eventsToDispatch = $this->collectEventsFor($value);
            foreach ($eventsToDispatch as $event) {
                $this->events->dispatch($event);
            }
            yield $value;
        }
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    private function collectEventsFor(mixed $value): array {
        if ($value instanceof PartialInferenceResponse) {
            return $this->collectResponseEvents($value);
        }
        if ($value instanceof AggregationState) {
            return $this->collectAggregateEvents($value);
        }
        return [];
    }

    private function collectResponseEvents(PartialInferenceResponse $response): array {
        $events = [];
        if ($response->contentDelta !== '') {
            $events[] = new ChunkReceived(['chunk' => $response->contentDelta]);
        }
        if ($response->value() !== null) {
            $events[] = new PartialResponseGenerated($response->value());
        }
        $events[] = new StreamedResponseReceived(['partial' => $response->toArray()]);
        return $events;
    }

    private function collectAggregateEvents(AggregationState $aggregate): array {
        // When aggregation is enabled, we no longer see raw PartialInferenceResponse
        // items here. If partial accumulation is turned on, use the last appended
        // partial to dispatch events exactly as before (preserves legacy behavior).
        $last = $aggregate->partials()->last();
        if ($last !== null) {
            return $this->collectResponseEvents($last);
        }

        // Fallback: if no accumulated partials are available but we do have
        // a latestValue, at least emit PartialResponseGenerated for UI updates.
        if ($aggregate->latestValue !== null) {
            return [new PartialResponseGenerated($aggregate->latestValue)];
        }

        return [];
    }
}
