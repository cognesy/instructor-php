<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\Evaluation;
use Cognesy\Instructor\Evaluation\Data\Feedback;

interface CanExplain {
    /** @return Feedback */
    public function feedback(Evaluation $evaluation) : Feedback;
}
