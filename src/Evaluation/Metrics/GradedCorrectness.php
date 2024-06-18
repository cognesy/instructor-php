<?php

namespace Cognesy\Instructor\Evaluation\Metrics;

use Cognesy\Instructor\Evaluation\Contracts\Metric;
use Cognesy\Instructor\Evaluation\Enums\CorrectnessGrade;

class GradedCorrectness implements Metric
{
    public function __construct(
        private CorrectnessGrade $grade,
    ) {}

    public function value(): CorrectnessGrade {
        return $this->grade;
    }

    public function toLoss() : float {
        return 1 - $this->grade->toFloat();
    }

    public function toScore() : float {
        return $this->grade->toFloat();
    }
}