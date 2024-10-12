<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanAggregateValues
{
    /**
     * Aggregate the given values into a single metric.
     *
     * @param Experiment $experiment
     * @return Metric
     */
    public function aggregate(Experiment $experiment): Metric;
}
