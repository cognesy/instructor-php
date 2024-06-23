<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;

interface CanQuantify
{
    public function quantify(PromptEvaluation $evaluation) : Metric;
}
