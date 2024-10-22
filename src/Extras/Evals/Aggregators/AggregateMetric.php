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
class AggregateMetric implements CanAggregateMetric
{
    public function __construct(
        private string                  $name = '',
        private string                  $metricName = '',
        private NumberAggregationMethod $method = NumberAggregationMethod::Mean,
    ) {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        if (empty($metricName)) {
            throw new \InvalidArgumentException('Metric name cannot be empty');
        }
    }

    public function name(): string {
        return $this->name;
    }

    public function aggregate(Experiment $experiment): Metric {
        $values = [];
        foreach ($experiment->metrics($this->metricName) as $metric) {
            $values[] = $metric->toFloat();
        }

        $aggregator = new NumberSeriesAggregator($values, [], $this->method);
        return new FloatMetric(
            name: $this->name,
            value: $aggregator->aggregate()
        );
    }
}