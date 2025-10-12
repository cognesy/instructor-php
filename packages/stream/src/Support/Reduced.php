<?php declare(strict_types=1);

namespace Cognesy\Stream\Support;

final readonly class Reduced {
    public function __construct(private mixed $value) {}

    public function value(): mixed {
        return $this->value;
    }
}