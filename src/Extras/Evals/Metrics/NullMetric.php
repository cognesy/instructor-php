<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Extras\Evals\Units\NoUnit;

class NullMetric implements Metric
{
    use Traits\HandlesMetric;

    public function __construct(
        string $name = 'none',
        ?Unit $unit = null,
    ) {
        $this->name = $name;
        $this->unit = $unit ?? new NoUnit();
        $this->value = 0;
    }
}
