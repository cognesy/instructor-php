<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Extras\Evals\Utils\NumberSeriesAggregator;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class AggregateWeightedObservation
{
    public function __construct(
        private array                   $keys = [],
        private array                   $weights = [],
        private NumberAggregationMethod $method = NumberAggregationMethod::Mean,
    ) {
        if (empty($keys)) {
            throw new \InvalidArgumentException('Metric name cannot be empty');
        }
    }

    /**
     * @param Observation[] $observations
     * @return float
     */
    public function aggregate(array $observations): float {
        $values = [];
        foreach ($observations as $observation) {
            foreach($this->keys as $key) {
                if ($observation->key() !== $key) {
                    continue;
                }
                $values[] = $observation->toFloat() * $this->weight($key);
            }
        }
        return (new NumberSeriesAggregator($values, [], $this->method))->aggregate();
    }

    // INTERNAL /////////////////////////////////////////////

    private function weight(string $name, float $default = 1.0): float {
        return $this->weights[$name] ?? $default;
    }
}
