<?php

namespace Cognesy\Instructor\Evaluation\Data;

use Cognesy\Instructor\Evaluation\Contracts\Metric;

class EvaluationResult
{
    public function __construct(
        private Metric $metric,
        private Feedback $feedback,
    ) {
    }

    public function metric() : Metric {
        return $this->metric;
    }

    public function feedback() : Feedback {
        return $this->feedback;
    }
}