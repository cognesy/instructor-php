<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;

interface CanProcessExperiment
{
    public function process(Experiment $experiment) : Experiment;
}
