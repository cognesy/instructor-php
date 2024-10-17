<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits;
use Cognesy\Instructor\Extras\Evals\Units\FloatUnit;

class FloatMetric implements Metric
{
    use Traits\HandlesMetric;

    public function __construct(
        string $name,
        float $value,
        string $symbol = '',
        int $precision = 2,
    ) {
        $this->name = $name;
        $this->unit = new FloatUnit($name, $symbol, $precision);
        $this->value = $value;
    }
}