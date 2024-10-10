<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Extras\Evals\Data\EvalOutput;

interface CanEvaluate
{
    public function evaluate(EvalInput $input) : EvalOutput;
}