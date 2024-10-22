<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Utils\Cli\Color;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class BaseMetric implements Metric
{
    private string $name;
    private mixed $value;
    private Unit $unit;

    public function name(): string {
        return $this->name;
    }

    public function unit(): Unit {
        return $this->unit;
    }

    public function value(): mixed {
        return $this->value;
    }

    public function toString(array $format = []): string {
        return $this->unit->toString($this->value, $format);
    }

    public function toFloat(): float {
        return $this->unit->toFloat($this->value);
    }

    public function toCliColor(): array {
        return [Color::GRAY];
    }

    public function toObservation(): Observation {
        return Observation::make(
            type: 'metric',
            key: $this->name,
            value: $this->value,
            metadata: [
                'class' => static::class,
                'unit' => $this->unit->name(),
                'color' => $this->toCliColor(),
                'string' => $this->toString(),
            ],
        );
    }
}