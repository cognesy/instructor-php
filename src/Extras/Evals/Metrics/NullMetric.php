<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;

class NullMetric implements Metric
{
    public function name(): string {
        return 'none';
    }

    public function value(): mixed {
        return 0;
    }

    public function toLoss(): float {
        return 0;
    }

    public function toScore(): float {
        return 0;
    }

    public function toString(): string {
        return 'n/a';
    }
}
