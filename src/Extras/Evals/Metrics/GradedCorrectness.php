<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Enums\CorrectnessGrade;

class GradedCorrectness implements Metric
{
    public function __construct(
        private string $name,
        private CorrectnessGrade $grade,
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function value(): CorrectnessGrade {
        return $this->grade;
    }

    public function toLoss() : float {
        return 1 - $this->grade->toFloat();
    }

    public function toScore() : float {
        return $this->grade->toFloat();
    }

    public function toString(): string {
        return $this->grade->value;
    }
}