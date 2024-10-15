<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Enums\CorrectnessGrade;
use Cognesy\Instructor\Extras\Evals\Units\StringEnumUnit;

class GradedCorrectness implements Metric
{
    use Traits\HandlesMetric;

    public function __construct(
        string $name,
        CorrectnessGrade $value,
    ) {
        $this->name = $name;
        $this->unit = new StringEnumUnit(CorrectnessGrade::class);
        $this->value = $value;
    }

    public function value(): CorrectnessGrade {
        return $this->value;
    }
}
