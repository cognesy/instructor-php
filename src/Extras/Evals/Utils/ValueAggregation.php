<?php

namespace Cognesy\Instructor\Extras\Evals\Utils;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Enums\ValueAggregationMethod;
use InvalidArgumentException;
use RuntimeException;

class ValueAggregation {
    private array $values = [];
    private array $weights = [];
    private ValueAggregationMethod $method;

    /**
     * ValueAggregator constructor.
     *
     * @param ValueAggregationMethod $method Aggregation method.
     */
    public function __construct(
        array $values = [],
        array $weights = [],
        ValueAggregationMethod $method = ValueAggregationMethod::Mean,
    ) {
        $this->method = $method;
        $this->values = $values;
        $this->weights = $weights;
    }

//    /**
//     * Creates a ValueAggregation instance from an array of metrics.
//     *
//     * @param array<Metric> $metrics Array of metrics.
//     * @param array<float> $weights Array of weights for each metric.
//     * @param ValueAggregationMethod $method Aggregation method.
//     * @return ValueAggregation The ValueAggregation instance.
//     */
//    public static function fromMetrics(
//        array $metrics,
//        array $weights = [],
//        ValueAggregationMethod $method = ValueAggregationMethod::Mean,
//    ): ValueAggregation {
//        $aggregator = new ValueAggregation(
//            values: [],
//            weights: $weights,
//            method: $method
//        );
//        foreach ($metrics as $metric) {
//            $aggregator->add($metric->name(), $metric->value(), $metric->weight());
//        }
//        return $aggregator;
//    }

//    /**
//     * Creates a ValueAggregation instance from an array of evaluations.
//     * @param array<Evaluation> $evaluations
//     * @param array<float> $weights
//     * @param ValueAggregationMethod $method
//     * @return ValueAggregation
//     */
//    public static function fromEvaluations(
//        array $evaluations,
//        array $weights = [],
//        ValueAggregationMethod $method = ValueAggregationMethod::Mean,
//    ) : ValueAggregation {
//        $aggregator = new ValueAggregation(
//            values: [],
//            weights: $weights,
//            method: $method
//        );
//        foreach ($evaluations as $evaluation) {
//            $aggregator->add(
//                name: $evaluation->metric()->name(),
//                value: $evaluation->metric()->toLoss(),
//                weight: $weights[$evaluation->metric()->name()] ?? 1.0
//            );
//        }
//        return $aggregator;
//    }

    /**
     * Adds a metric with an optional weight.
     *
     * @param string $name Name of the metric.
     * @param float $value Value of the metric.
     * @param float|null $weight Weight for the metric (required for weighted_mean).
     * @return void
     * @throws InvalidArgumentException If weight is not provided for weighted_mean.
     */
    public function add(string $name, float $value, float $weight = null): void {
        $this->values[$name] = $value;

        if ($this->method === ValueAggregationMethod::WeightedMean) {
            if ($weight === null) {
                throw new InvalidArgumentException("Weight must be provided for weighted_mean aggregation.");
            }
            $this->weights[$name] = $weight;
        }
    }

    /**
     * Calculates the aggregate value based on the selected method.
     *
     * @return float The aggregate value.
     * @throws RuntimeException If no values are added or weights are missing for weighted_mean.
     */
    public function value(): float {
        if (empty($this->values)) {
            throw new RuntimeException("No values to aggregate.");
        }
        return match ($this->method) {
            ValueAggregationMethod::Mean => $this->calculateMean(),
            ValueAggregationMethod::WeightedMean => $this->calculateWeightedMean(),
            ValueAggregationMethod::Sum => $this->calculateSum(),
            ValueAggregationMethod::Max => $this->calculateMax(),
            ValueAggregationMethod::Min => $this->calculateMin(),
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Calculates the arithmetic mean of values.
     *
     * @return float The mean.
     */
    private function calculateMean(): float {
        return array_sum($this->values) / count($this->values);
    }

    /**
     * Calculates the weighted mean of values.
     *
     * @return float The weighted mean.
     * @throws RuntimeException If total weight is zero.
     */
    private function calculateWeightedMean(): float {
        if (count($this->weights) !== count($this->values)) {
            throw new RuntimeException("All loss metrics must have weights for weighted_mean aggregation.");
        }

        $aggregate = 0.0;
        $totalWeight = 0.0;

        foreach ($this->values as $key => $value) {
            $weight = $this->weights[$key] ?? 0.0;
            $aggregate += $weight * $value;
            $totalWeight += $weight;
        }

        if ($totalWeight == 0.0) {
            throw new RuntimeException("Total weight must not be zero.");
        }

        return $aggregate / $totalWeight;
    }

    /**
     * Calculates the sum of values.
     *
     * @return float The sum.
     */
    private function calculateSum(): float {
        return array_sum($this->values);
    }

    /**
     * Finds the maximum value.
     *
     * @return float The maximum value.
     */
    private function calculateMax(): float {
        return max($this->values);
    }

    /**
     * Finds the minimum value.
     *
     * @return float The minimum value.
     */
    private function calculateMin(): float {
        return min($this->values);
    }
}
