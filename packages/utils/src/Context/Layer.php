<?php declare(strict_types=1);

namespace Cognesy\Utils\Context;

use Closure;

final class Layer
{
    /** @var Closure(Context): Context */
    private Closure $builder;

    /**
     * @param Closure(Context): Context $builder
     */
    private function __construct(Closure $builder) {
        $this->builder = $builder;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param T $service
     */
    public static function provides(string $class, object $service): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $service),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param callable(Context):T $factory
     */
    public static function providesFrom(string $class, callable $factory): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $factory($ctx)),
        );
    }

    /**
     * Provide a qualified service via a typed key.
     *
     * @template T of object
     * @param Key<T> $key
     * @param T $service
     */
    public static function providesKey(Key $key, object $service): self {
        return new self(
            static fn(Context $ctx) => $ctx->withKey($key, $service),
        );
    }

    /**
     * Provide a qualified service via a factory and typed key.
     *
     * @template T of object
     * @param Key<T> $key
     * @param callable(Context):T $factory
     */
    public static function providesFromKey(Key $key, callable $factory): self {
        return new self(
            static fn(Context $ctx) => $ctx->withKey($key, $factory($ctx)),
        );
    }

    /**
     * Sequential composition – **other** builds first, **$this** builds second.
     */
    public function dependsOn(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            return $this->applyTo($other->applyTo($ctx));
        });
    }

    /**
     * Sequential composition – **this** builds first, **$other** builds second.
     */
    public function referredBy(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            return $other->applyTo($this->applyTo($ctx));
        });
    }

    /** Parallel merge (right‑bias). Both layers see the same incoming Context. */
    public function merge(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            $ctxLeft = $this->applyTo($ctx);
            $ctxRight = $other->applyTo($ctx);
            return $ctxLeft->merge($ctxRight); // right‑bias via Context::merge()
        });
    }

    /** Execute layer against a base context. */
    public function applyTo(Context $context): Context {
        return ($this->builder)($context);
    }
}
