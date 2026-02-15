<?php declare(strict_types=1);

namespace Cognesy\Utils\Container;

use Closure;
use Cognesy\Utils\Container\Exceptions\ContainerException;
use Cognesy\Utils\Container\Exceptions\NotFoundException;
use Illuminate\Contracts\Container\Container as IlluminateContainer;

final class LaravelContainer implements Container
{
    public function __construct(
        private IlluminateContainer $app,
    ) {}

    #[\Override]
    public function get(string $id): mixed {
        try {
            return $this->app->make($id);
        } catch (\Illuminate\Contracts\Container\BindingResolutionException $e) {
            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (\Throwable $e) {
            throw new ContainerException($e->getMessage(), previous: $e);
        }
    }

    #[\Override]
    public function has(string $id): bool {
        return $this->app->bound($id) || $this->app->has($id);
    }

    /** @param Closure(Container): mixed $factory */
    #[\Override]
    public function set(string $id, Closure $factory): void {
        $this->app->bind($id, fn ($app) => $factory(new self($app)));
    }

    /** @param Closure(Container): mixed $factory */
    #[\Override]
    public function singleton(string $id, Closure $factory): void {
        $this->app->singleton($id, fn ($app) => $factory(new self($app)));
    }

    #[\Override]
    public function instance(string $id, object $service): void {
        $this->app->instance($id, $service);
    }
}
