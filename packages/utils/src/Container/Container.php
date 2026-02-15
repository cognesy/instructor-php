<?php declare(strict_types=1);

namespace Cognesy\Utils\Container;

use Closure;
use Psr\Container\ContainerInterface;

interface Container extends ContainerInterface
{
    /**
     * Register a transient factory — called every time get() is invoked.
     */
    public function set(string $id, Closure $factory): void;

    /**
     * Register a singleton factory — called once, result cached.
     */
    public function singleton(string $id, Closure $factory): void;

    /**
     * Register a pre-built instance directly.
     */
    public function instance(string $id, object $service): void;
}
