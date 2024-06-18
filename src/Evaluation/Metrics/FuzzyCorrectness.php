<?php

namespace Cognesy\Instructor\Evaluation\Metrics;

use Cognesy\Instructor\Evaluation\Contracts\Metric;

class FuzzyCorrectness implements Metric
{
    public function __construct(
        private float $correctness,
    ) {}

    public function value(): float {
        return $this->correctness;
    }

    public function toLoss() : float {
        return 1 - $this->correctness;
    }

    public function toScore() : float {
        return $this->correctness;
    }
}