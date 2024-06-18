<?php

namespace Cognesy\Instructor\Evaluation\Contracts;

use Cognesy\Instructor\Evaluation\Data\Feedback;

interface CanProvideFeedback {
    /** @return Feedback */
    public function feedback() : Feedback;
}
