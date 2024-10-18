<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateExperimentMetrics;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\NullMetric;

class NullAggregator implements CanAggregateExperimentMetrics
{
    public function aggregate(Experiment $experiment): Metric {
        return new NullMetric();
    }
}