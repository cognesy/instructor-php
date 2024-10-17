<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Extras\Evals\Units\CountUnit;
use Cognesy\Instructor\Utils\Cli\Color;

class MatchCount implements Metric
{
    private string $name;
    private Unit $unit;

    public function __construct(
        string $name,
        private int $matches,
        private int $total,
    ) {
        $this->name = $name;
        $this->unit = new CountUnit();
    }

    public function value() : float {
        return $this->matches;
    }

    public function name(): string {
        return $this->name;
    }

    public function unit(): Unit {
        return $this->unit;
    }

    public function toString(): string {
        return "{$this->matches}/{$this->total}";
    }

    public function toFloat(): float {
        return $this->matches / $this->total;
    }

    public function toCliColor(): array {
        return [Color::GRAY];
    }
}
