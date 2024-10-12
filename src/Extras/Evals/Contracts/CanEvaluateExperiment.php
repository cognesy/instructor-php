<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanEvaluateExperiment
{
    public function evaluate(Experiment $experiment) : Evaluation;
}