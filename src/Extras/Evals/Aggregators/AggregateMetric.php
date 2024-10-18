<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateExperimentMetrics;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Enums\ValueAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\FloatMetric;
use Cognesy\Instructor\Extras\Evals\Utils\ValueAggregator;

class AggregateMetric implements CanAggregateExperimentMetrics
{
    public function __construct(
        private string $name = '',
        private string $metricName = '',
        private ValueAggregationMethod $method = ValueAggregationMethod::Mean,
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
        foreach ($experiment->evaluations($this->metricName) as $evaluation) {
            $values[] = $evaluation->metric->toFloat();
        }

        $aggregator = new ValueAggregator($values, [], $this->method);
        return new FloatMetric(
            name: $this->name,
            value: $aggregator->aggregate()
        );
    }
}