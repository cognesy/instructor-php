<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use ReflectionClass;

class StringEnumUnit implements Unit
{
    private mixed $enum;
    private string $name;

    public function __construct(string $enumClass) {
        $this->name = (new ReflectionClass($enumClass))->getShortName();
        $this->enum = new $enumClass;
    }

    public function name(): string {
        return $this->name;
    }

    public function isValid(mixed $value): bool {
        return match(true) {
            !is_string($value) => false,
            $this->enum->tryFrom($value) !== null => true,
            default => false,
        };
    }

    public function toString(mixed $value, array $format = []): string {
        return $this->enum->value;
    }

    public function toFloat(mixed $value): float {
        // if has method toFloat
        if (method_exists($this->enum, 'toFloat')) {
            return $this->enum->toFloat();
        }
        return 0.0;
    }
}
