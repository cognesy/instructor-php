<?php declare(strict_types=1);

namespace Cognesy\Config\Contracts;

interface CanProvideConfig
{
    /**
     * Returns a config value for the path or provided default when missing.
     *
     * Implementations should not throw for missing keys when $default is provided.
     */
    public function get(string $path, mixed $default = null): mixed;

    /**
     * Returns true when the path is known to exist.
     *
     * Providers should keep has()/get() consistent for the same path.
     */
    public function has(string $path): bool;
}
