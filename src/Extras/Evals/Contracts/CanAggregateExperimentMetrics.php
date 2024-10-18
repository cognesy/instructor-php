<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanAggregateExperimentMetrics
{
    public function name() : string;
    public function aggregate(Experiment $experiment) : Metric;
}
