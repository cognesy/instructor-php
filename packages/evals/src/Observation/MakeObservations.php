<?php declare(strict_types=1);

namespace Cognesy\Evals\Observation;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Contracts\CanObserveExecution;
use Cognesy\Evals\Contracts\CanObserveExperiment;
use Exception;

/**
 * Makes observations based on an observed subject and a set of observers.
 */
class MakeObservations
{
    public function __construct(
        private mixed $subject,
        private array $observers = [],
    ) {}

    /**
     * Creates a new instance of the class with the given subject.
     *
     * @param mixed $subject The subject to be assigned to the new instance.
     *
     * @return self Returns a new instance of the class.
     */
    public static function for(mixed $subject) : self {
        return new self(subject: $subject);
    }

    /**
     * Sets the observers for the current instance.
     *
     * @param array $observers An array of observers to be assigned.
     *
     * @return self Returns the current instance with the updated observers.
     */
    public function withObservers(array $observers) : self {
        if (is_array($observers[0] ?? null)) {
            $observers = array_merge(...array_values($observers));
        }
        $this->observers = $observers;
        return $this;
    }

    /**
     * Retrieves all observations from the current context.
     *
     * @return array List of all observations.
     */
    public function all() : array {
        return $this->observations();
    }

    /**
     * Retrieves observations for the given types of observers.
     *
     * @param array $types List of observer types to generate the observations for.
     * @return array List of observations.
     */
    public function only(array $types) : array {
        return $this->observations($types);
    }

    // INTERNAL ////////////////////////////////////////////////

    private function observations(?array $types = null) : array {
        $sources = [];
        foreach ($this->observers($this->observers, $types) as $observer) {
            $sources[] = match(true) {
                $observer instanceof CanGenerateObservations => $this->wrapGenerator($observer, $this->subject),
                $observer instanceof CanObserveExperiment => $this->wrapObservation($observer->observe(...), $this->subject),
                $observer instanceof CanObserveExecution => $this->wrapObservation($observer->observe(...), $this->subject),
                default => throw new Exception('Invalid observation source: ' . get_class($observer)),
            };
        }
        return $this->getObservations($sources);
    }

    private function getObservations(iterable $sources) : array {
        // filter out empty items and turn array<Observation[]> to Observation[]
        $result = [];
        foreach ($sources as $observer) {
            foreach ($observer as $observation) {
                $result[] = $observation;
            }
        }
        return $result;
    }

    /**
     * @param callable(object):\Cognesy\Evals\Observation $callback
     * @param object $subject
     * @return iterable<\Cognesy\Evals\Observation>
     */
    private function wrapObservation(callable $callback, mixed $subject) : iterable {
        if ($subject !== null) {
            $result = $callback($subject);
            if ($result !== null) {
                yield $result;
            }
        }
    }

    /**
     * @param \Cognesy\Evals\Contracts\CanGenerateObservations $generator
     * @param object $subject
     * @return iterable<\Cognesy\Evals\Observation>
     */
    private function wrapGenerator(CanGenerateObservations $generator, mixed $subject) : iterable {
        if ($generator->accepts($subject)) {
            yield from $generator->observations($subject);
        }
    }

    /**
     * @return array<object>
     */
    private function observers(array $observers, ?array $types = null) : array {
        $instances = $this->makeInstances($observers);
        return match(true) {
            empty($types) => $instances,
            default => array_filter($instances, fn($instance) => $this->isOneOf($instance, $types)),
        };
    }

    private function makeInstances(array $observers) : array {
        $instances = [];
        foreach ($observers as $observer) {
            $instances[] = match(true) {
                is_string($observer) => new $observer,
                is_object($observer) => $observer,
                default => throw new Exception('Invalid observation source type: ' . gettype($observer)),
            };
        }
        return $instances;
    }

    private function isOneOf(object $observer, array $types) : bool {
        return array_reduce(
            array: $types,
            callback: fn($carry, $type) => $carry || is_a($observer, $type, true),
            initial: false
        );
    }
}