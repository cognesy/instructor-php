<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanMeasureExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\Generic\NullMetric;

class NullAggregator implements CanMeasureExperiment
{
    public function measure(Experiment $experiment): Metric {
        return new NullMetric();
    }
}