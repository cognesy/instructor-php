<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanMeasureExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Enums\ValueAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\FloatMetric;
use Cognesy\Instructor\Extras\Evals\Utils\ValueAggregator;

class AggregateMetric implements CanMeasureExperiment
{
    public function __construct(
        private string $name = 'reliability',
        private string $metricName = 'correctness',
        private ValueAggregationMethod $method = ValueAggregationMethod::Mean,
    ) {
    }

    public function name(): string {
        return $this->name;
    }

    public function measure(Experiment $experiment): Metric {
        $values = [];
        foreach ($experiment->evaluations($this->metricName) as $evaluation) {
            $values[] = $evaluation->metric->toFloat();
        }

        $aggregator = new ValueAggregator($values, $this->method);
        return new FloatMetric(
            name: $this->name,
            value: $aggregator->aggregate()
        );
    }
}