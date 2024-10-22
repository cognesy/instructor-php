<?php

namespace Cognesy\Instructor\Extras\Evals;

class SelectObservations
{
    private function __construct(
        private array $observations
    ) {}

    public static function from(array $sources) : self {
        return new self(array_merge(...$sources));
    }

    public function withKeys(array $keys) : self {
        return new SelectObservations(
            array_filter(
                $this->observations,
                fn($observation) => in_array($observation->key(), $keys)
            )
        );
    }

    public function withTypes(array $types) : self {
        return new SelectObservations(
            array_filter(
                $this->observations,
                fn($observation) => in_array($observation->type(), $types)
            )
        );
    }

    public function filter(callable $callback) : self {
        return new SelectObservations(
            array_filter($this->observations, $callback)
        );
    }

    public function sort(callable $callback) : self {
        $observations = $this->observations;
        usort($observations, $callback);
        return new SelectObservations($observations);
    }

    public function first() : ?Observation {
        return reset($this->observations) ?: null;
    }

    public function sole() : Observation {
        if (count($this->observations) !== 1) {
            throw new \Exception('Expected exactly one observation, got ' . count($this->observations));
        }
        return reset($this->observations);
    }

    public function hasAny() : bool {
        return count($this->observations) > 0;
    }

    /**
     * @return Observation[]
     */
    public function all() : array {
        return $this->observations;
    }

    /**
     * @return Observation[]
     */
    public function get(string $key = null) : array {
        return match(true) {
            ($key === null) => $this->observations,
            default => (new self($this->observations))->withKeys([$key])->all(),
        };
    }
}