<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\Evaluation;

interface CanEvaluateResult
{
    public function process(Evaluation $evaluation) : Evaluation;
}