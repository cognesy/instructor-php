<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanMeasureExperiment
{
    public function name() : string;
    public function measure(Experiment $experiment) : Metric;
}
