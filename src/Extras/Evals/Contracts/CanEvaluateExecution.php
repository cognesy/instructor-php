<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Execution;

interface CanEvaluateExecution
{
    public function evaluate(Execution $execution) : Evaluation;
}