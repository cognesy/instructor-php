<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;

class MatchCount implements Metric
{
    public function __construct(
        private string $name,
        private int $matches,
        private int $total,
    ) {}

    public function name() : string {
        return $this->name;
    }

    public function value() : float {
        return $this->matches / $this->total;
    }

    public function toLoss() : float {
        return 1 - $this->value();
    }

    public function toScore() : float {
        return $this->value();
    }

    public function toString(): string {
        return "{$this->matches}/{$this->total}";
    }
}
