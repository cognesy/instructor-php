<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits\HandlesMetric;
use Cognesy\Instructor\Extras\Evals\Units\IntUnit;

class IntMetric
{
    use HandlesMetric;

    public function __construct(
        string $name,
        float $value,
        Unit $unit = null,
        string $symbol = '',
    ) {
        $this->name = $name;
        $this->unit = $unit ?? new IntUnit($name, $symbol);
        $this->value = $value;
    }
}