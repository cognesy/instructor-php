<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateValues;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\NullMetric;

class SelectedMetric implements CanAggregateValues
{
    public function __construct(
        private string $name
    ) {}

    public function aggregate(Experiment $experiment): Metric {
        foreach ($experiment->evaluations as $evaluation) {
            if ($evaluation->metric->name() === $this->name) {
                return $evaluation->metric;
            }
        }
        return new NullMetric();
    }
}
