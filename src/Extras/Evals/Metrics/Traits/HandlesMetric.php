<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Traits;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Utils\Cli\Color;

trait HandlesMetric
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

    public function jsonSerialize(): array {
        $customFields = $this->toArray();
        return [
            'type' => 'metric',
            'key' => $this->name,
            'value' => $this->value,
            'metadata' => [
                'class' => static::class,
                'unit' => $unit,
                'customFields' => $customFields,
            ],
        ];
    }
}
