<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Events;

use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Partials\Data\AggregatedResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Iterator;
use IteratorAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

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
        private EventDispatchPolicy $policy = new EventDispatchPolicy(),
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        $batch = [];
        foreach ($this->inner as $value) {
            // Skip if silent mode
            if ($this->policy->mode === EventDispatchMode::Silent) {
                yield $value;
                continue;
            }
            // Collect events for this value
            $eventsToDispatch = $this->collectEventsFor($value);
            // Apply filter if configured
            if ($this->policy->filter !== null) {
                $eventsToDispatch = array_filter($eventsToDispatch, $this->policy->filter);
            }
            // Batch if configured
            if ($this->policy->batchSize > 1) {
                $batch = [...$batch, ...$eventsToDispatch];
                if (count($batch) >= $this->policy->batchSize) {
                    $this->dispatchBatch($batch);
                    $batch = [];
                }
            } else {
                // Immediate dispatch
                $this->dispatchBatch($eventsToDispatch);
            }

            yield $value;
        }
        // Flush remaining batch
        if (!empty($batch)) {
            $this->dispatchBatch($batch);
        }
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    private function dispatchBatch(array $events): void {
        foreach ($events as $event) {
            try {
                $this->events->dispatch($event);
            } catch (Throwable $e) {
                $this->handleDispatchError($e);
            }
        }
    }

    private function handleDispatchError(Throwable $e): void {
        match($this->policy->mode) {
            EventDispatchMode::Strict => throw $e,
            EventDispatchMode::Lenient => $this->policy->onError?->__invoke($e),
            EventDispatchMode::Silent => null,
        };
    }

    private function collectEventsFor(mixed $value): array {
        if ($value instanceof PartialInferenceResponse) {
            return $this->collectResponseEvents($value);
        }
        if ($value instanceof AggregatedResponse) {
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

    private function collectAggregateEvents(AggregatedResponse $aggregate): array {
        // Could dispatch aggregate-specific events here if needed
        return [];
    }
}
