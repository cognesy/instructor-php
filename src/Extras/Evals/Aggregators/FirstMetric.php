<?php

namespace Cognesy\Instructor\Extras\Evals\Aggregators;

use Cognesy\Instructor\Extras\Evals\Contracts\CanAggregateValues;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\NullMetric;

class FirstMetric implements CanAggregateValues
{
    public function aggregate(Experiment $experiment): Metric {
        $firstEval = $experiment->evaluations[0] ?? null;
        return $firstEval ? $firstEval->metric : new NullMetric();
    }
}