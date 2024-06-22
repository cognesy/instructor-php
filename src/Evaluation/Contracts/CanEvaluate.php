<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\Evaluation;
use Cognesy\Instructor\Evaluation\Data\EvaluationResult;

interface CanEvaluate
{
    public function process(Evaluation $evaluation) : EvaluationResult;
}