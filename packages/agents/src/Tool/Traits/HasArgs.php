<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

trait HasArgs
{
    protected function arg(array $args, string $name, int $position, mixed $default = null): mixed {
        return $args[$name] ?? $args[$position] ?? $default;
    }
}
