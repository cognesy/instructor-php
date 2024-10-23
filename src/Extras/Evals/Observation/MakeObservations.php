<?php

namespace Cognesy\Instructor\Extras\Evals\Observation;

use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanProvideExecutionObservations;
use Cognesy\Instructor\Extras\Evals\Contracts\CanSummarizeExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanSummarizeExperiment;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observation;
use Exception;

class MakeObservations
{
    public function __construct(
        private $sources = [],
        private ?Experiment $experiment = null,
        private ?Execution $execution = null,
    ) {}

    public static function for(Experiment|Execution $subject) : self {
        return new self(
            experiment: $subject instanceof Experiment ? $subject : null,
            execution: $subject instanceof Execution ? $subject : null,
        );
    }

    public function withSources(array $sources) : self {
        if (is_array($sources[0] ?? null)) {
            $sources = array_merge(...$sources);
        }
        $this->sources = $sources;
        return $this;
    }

    public function all() : array {
        return $this->observations();
    }

    public function only(array $types) : array {
        return $this->observations($types);
    }

    // INTERNAL ////////////////////////////////////////////////

    public function observations(array $types = null) : array {
        $observations = [];
        foreach ($this->sources($this->sources, $types) as $source) {
            $observations[] = match(true) {
                $source instanceof CanProvideExecutionObservations => $source->observations($this->execution),
                $source instanceof CanObserveExperiment => $this->wrapObservation($source->observe(...), $this->experiment),
                $source instanceof CanSummarizeExperiment => $this->wrapObservation($source->summarize(...), $this->experiment),
                $source instanceof CanObserveExecution => $this->wrapObservation($source->observe(...), $this->execution),
                $source instanceof CanSummarizeExecution => $this->wrapObservation($source->summarize(...), $this->execution),
                default => throw new Exception('Invalid observation source: ' . get_class($source)),
            };
        }
        // filter out empty items, then turn array<Observation[]> to Observation[]
        return array_merge(...array_filter($observations));
    }

    /**
     * @param callable $callback
     * @param object $subject
     * @return Observation<array>
     */
    private function wrapObservation(callable $callback, ?object $subject) : array {
        if ($subject === null) {
            return [];
        }
        return [$callback($subject)];
    }

    private function sources(array $sources, array $types = null) : iterable {
        $instances = $this->makeInstances($sources);
        return match(true) {
            empty($types) => $instances,
            default => array_filter($instances, fn($instance) => $this->isOneOf($instance, $types)),
        };
    }

    private function makeInstances(array $sources) : array {
        $instances = [];
        foreach ($sources as $source) {
            $instances[] = match(true) {
                is_string($source) => new $source,
                is_object($source) => $source,
                default => throw new Exception('Invalid observation source type: ' . gettype($source)),
            };
        }
        return $instances;
    }

    private function isOneOf(object $source, array $types) : bool {
        return array_reduce(
            array: $types,
            callback: fn($carry, $type) => $carry || is_a($source, $type, true),
            initial: false
        );
    }
}