<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Evaluation\Data\Feedback;

interface CanExplain {
    /** @return Feedback */
    public function feedback(PromptEvaluation $evaluation) : Feedback;
}
