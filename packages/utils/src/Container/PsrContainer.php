<?php declare(strict_types=1);

namespace Cognesy\Utils\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * Wraps any read-only PSR-11 container and overlays a SimpleContainer for writes.
 *
 * get()/has() check the local overlay first, then fall through to the inner container.
 * set()/singleton()/instance() always write to the local overlay.
 */
final class PsrContainer implements Container
{
    private SimpleContainer $local;

    public function __construct(
        private ContainerInterface $inner,
    ) {
        $this->local = new SimpleContainer();
    }

    #[\Override]
    public function get(string $id): mixed {
        if ($this->local->has($id)) {
            return $this->local->get($id);
        }
        return $this->inner->get($id);
    }

    #[\Override]
    public function has(string $id): bool {
        return $this->local->has($id) || $this->inner->has($id);
    }

    #[\Override]
    public function set(string $id, Closure $factory): void {
        $this->local->set($id, $factory);
    }

    #[\Override]
    public function singleton(string $id, Closure $factory): void {
        $this->local->singleton($id, $factory);
    }

    #[\Override]
    public function instance(string $id, object $service): void {
        $this->local->instance($id, $service);
    }
}
