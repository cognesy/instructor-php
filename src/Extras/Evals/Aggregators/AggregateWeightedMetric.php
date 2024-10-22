<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateMetric;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\FloatMetric;
use Cognesy\Instructor\Extras\Evals\Utils\NumberSeriesAggregator;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class AggregateWeightedMetric implements CanAggregateMetric
{
    public function __construct(
        private string                  $name = '',
        private array                   $metricNames = [],
        private array                   $weights = [],
        private NumberAggregationMethod $method = NumberAggregationMethod::Mean,
    ) {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        if (empty($metricNames)) {
            throw new \InvalidArgumentException('Metric name cannot be empty');
        }
    }

    public function name(): string {
        return $this->name;
    }

    public function aggregate(Experiment $experiment): Metric {
        $values = [];
        foreach($this->metricNames as $metricName) {
            foreach ($experiment->metrics($metricName) as $metric) {
                $values[] = $metric->toFloat() * $this->weight($metricName, 1.0);
            }
        }

        $aggregator = new NumberSeriesAggregator($values, [], $this->method);
        return new FloatMetric(
            name: $this->name,
            value: $aggregator->aggregate()
        );
    }

    // INTERNAL /////////////////////////////////////////////

    private function weight(string $name, float $default = 1.0): float {
        return $this->weights[$name] ?? $default;
    }
}