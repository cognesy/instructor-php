<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Units\BooleanUnit;
use Cognesy\Instructor\Utils\Cli\Color;

class BooleanCorrectness implements Metric
{
    use Traits\HandlesMetric;

    public function __construct(
        bool $value,
        string $name = 'correct',
    ) {
        $this->name = $name;
        $this->unit = new BooleanUnit();
        $this->value = $value;
    }

    public function toString(): string {
        return $this->value ? 'OK' : 'FAIL';
    }

    public function toCliColor(): array {
        return $this->value ? [Color::BG_GREEN, Color::WHITE] : [Color::BG_RED, Color::WHITE];
    }
}