<?php declare(strict_types=1);

namespace Cognesy\Config\Contracts;

interface CanProvideConfig
{
    public function get(string $path, mixed $default = null): mixed;
    public function has(string $path): bool;
}