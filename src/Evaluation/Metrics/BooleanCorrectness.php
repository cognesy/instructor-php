<?php

namespace Cognesy\Instructor\Evaluation\Metrics;

use Cognesy\Instructor\Evaluation\Contracts\Metric;

class BooleanCorrectness implements Metric
{
    public function __construct(
        private bool $value,
    ) {}

    public function value(): bool {
        return $this->value;
    }

    public function toLoss(): float {
        return $this->value() ? 0 : 1;
    }

    public function toScore(): float {
        return $this->value() ? 1 : 0;
    }
}