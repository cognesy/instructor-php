<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Utils\Cli\Color;

class BooleanCorrectness implements Metric
{
    public function __construct(
        private bool $value,
        private string $name = 'correct',
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function value(): mixed {
        return $this->value;
    }

    public function toLoss(): float {
        return $this->value ? 0 : 1;
    }

    public function toScore(): float {
        return $this->value ? 1 : 0;
    }

    public function toString(): string {
        return $this->value ? 'OK' : 'FAIL';
    }

    public function toCliColor(): array {
        return $this->value ? [Color::BG_GREEN, Color::WHITE] : [Color::BG_RED, Color::WHITE];
    }
}