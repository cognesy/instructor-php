<?php

namespace Cognesy\Instructor\Evaluation\Metrics;

use Cognesy\Instructor\Evaluation\Contracts\Metric;

class ExactValueMatch implements Metric
{
    public function __construct(
        private int $matches,
        private int $total,
    ) {}

    public function value() : float {
        return $this->matches / $this->total;
    }

    public function toLoss() : float {
        return 1 - $this->value();
    }

    public function toScore() : float {
        return $this->value();
    }
}