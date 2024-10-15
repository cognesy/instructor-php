<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanMeasureExperiment
{
    public function evaluate(Experiment $experiment) : Metric;
}
