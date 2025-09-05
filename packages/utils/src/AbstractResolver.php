<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Closure;
use RuntimeException;

/**
 * @template TAccepted
 */
abstract class AbstractResolver
{
    /** @var Closure[] */
    private array $pipeline;

    private bool $suppressErrors;
    private mixed $cached = null;

    /**
     * @param list<callable|object> $providers ordered by priority (first wins)
     */
    public function __construct(array $providers, bool $suppressErrors = true) {
        if ($providers === []) {
            throw new RuntimeException('Resolver needs at least one provider.');
        }

        $this->pipeline = array_map(fn($provider) => $this->defer($provider), $providers);
        $this->suppressErrors = $suppressErrors;
    }

    abstract protected function accepts(mixed $candidate): bool;

    protected function defer(callable|object $provider): Closure {
        return $provider instanceof Closure
            ? $provider
            : static fn() => (is_callable($provider) ? $provider() : $provider);
    }

    final protected function resolve(): mixed {
        if ($this->cached !== null) {
            return $this->cached;
        }

        foreach ($this->pipeline as $factory) {
            try {
                $candidate = $factory();
            } catch (\Throwable $e) {
                if (!$this->suppressErrors) {
                    throw $e;
                }
                continue;
            }

            if ($this->accepts($candidate)) {
                return $this->cached = $candidate;
            }
        }

        throw new RuntimeException(static::class . ': no provider produced an acceptable value.');
    }
}
