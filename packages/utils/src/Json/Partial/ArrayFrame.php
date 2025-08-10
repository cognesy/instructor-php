<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final class ArrayFrame implements ParseFrame
{
    /** @var array<int,mixed> */
    private array $value = [];

    public function addValue(mixed $val): void {
        $this->value[] = $val;
    }

    /** @return array<int,mixed> */
    public function getValue(): array {
        return $this->value;
    }
}