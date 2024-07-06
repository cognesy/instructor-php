<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Evaluation\Data\EvaluationResult;

interface CanEvaluate
{
    public function evaluate(PromptEvaluation $evaluation) : EvaluationResult;
}