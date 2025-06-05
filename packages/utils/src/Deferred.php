<?php

namespace Cognesy\Utils;

use Closure;
use ReflectionObject;
use RuntimeException;

class Deferred
{
    private Closure $deferred;
    private mixed $instance;

    public function __construct(callable|Closure|null $deferred = null) {
        if ($deferred !== null) {
            $this->defer($deferred);
        }
    }

    public function isSet() : bool {
        return !isset($this->deferred);
    }

    public function defer(callable|Closure $deferred) : void {
        $this->deferred = match (true) {
            $deferred instanceof Closure => $deferred,
            is_callable($deferred) => Closure::fromCallable($deferred),
            default => throw new RuntimeException('Deferred must be a callable or closure.'),
        };
        $this->instance = null;
    }

    public function resolve() : mixed {
        if (isset($this->instance)) {
            return $this->instance;
        }

        if (!isset($this->deferred)) {
            throw new RuntimeException('Deferred closure not set.');
        }

        $this->instance = ($this->deferred)();

        return $this->instance;
    }

    public function resolveUsing(...$args) : mixed {
        if (isset($this->instance)) {
            return $this->instance;
        }

        if (!isset($this->deferred)) {
            throw new RuntimeException('Deferred closure not set.');
        }

        $this->instance = ($this->deferred)(...$args);

        return $this->instance;
    }

    public function __toString() : string {
        return $this->instance
            ? (new ReflectionObject($this->instance))->getShortName()
            : '(unresolved)';
    }
}