<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanProcessExperiment
{
    public function process(Experiment $experiment) : void;
}
