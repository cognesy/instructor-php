<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits;
use Cognesy\Instructor\Extras\Evals\Units\PercentageUnit;

class PercentageMetric implements Metric
{
    use Traits\HandlesMetric;

    public function __construct(
        string $name,
        float|int $value,
    ) {
        $this->name = $name;
        $this->unit = new PercentageUnit();
        if (!$this->unit->isValid($value)) {
            throw new \InvalidArgumentException("Invalid value for percentage metric: $value");
        }
        $this->value = $value;
    }
}