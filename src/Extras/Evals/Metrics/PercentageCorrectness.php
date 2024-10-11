<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Utils\Cli\Color;

class PercentageCorrectness implements Metric
{
    public function __construct(private float $value) {
        if ($value < 0 || $value > 1) {
            throw new \Exception('Percentage value must be between 0 and 1');
        }
    }

    public function value(): mixed {
        return $this->value;
    }

    public function toLoss(): float {
        return 1 - $this->value;
    }

    public function toScore(): float {
        return $this->value;
    }

    public function toString(): string {
        return number_format($this->value * 100, 2) . '%';
    }

    public function toCliColor(): array {
        return match(true) {
            $this->value < 0.25 => [Color::BG_BLACK, Color::RED],
            $this->value < 0.5 => [Color::BG_RED, Color::WHITE],
            $this->value < 0.75 => [Color::BG_YELLOW, Color::BLACK],
            default => [Color::BG_GREEN, Color::WHITE],
        };
    }
}
