<?php

namespace Cognesy\Instructor\Utils;

use Closure;

class Chain
{
    private ?Result $carry;
    private ?Closure $source;
    private array $onFailure = [];
    private array $processors;

    public function __construct(Result $value = null, Closure $source = null, array $processors = []) {
        $this->carry = $value;
        $this->source = $source;
        $this->processors = $processors;
    }

    static public function make() : Chain {
        return new Chain();
    }

    static public function for(mixed $value): Chain {
        return new Chain(Result::with($value));
    }

    static public function from(callable $source): Chain {
        return new Chain(null, $source);
    }

    public function through(callable $processor): Chain {
        $this->processors[] = function($carry) use ($processor) {
            $result = match(true) {
                $carry instanceof Result => $processor($carry->unwrap()),
                ($carry === null) => $processor(),
                default => $processor($carry),
            };
            return match(true) {
                $result instanceof Result => $result,
                ($result === null) => Result::failure("Callback returned null"),
                default => Result::success($result),
            };
        };
        return $this;
    }

    public function tap(callable $processor): Chain {
        $this->processors[] = function($carry) use ($processor) {
            $result = match(true) {
                $carry instanceof Result => $processor($carry->unwrap()),
                ($carry === null) => $processor(),
                default => $processor($carry),
            };
            return $carry;
        };
        return $this;
    }

    public function onFailure(callable $handler) : Chain {
        $this->onFailure[] = function($result) use ($handler) {
            if ($result->isFailure()) {
                $handler($result);
            }
        };
        return $this;
    }

    public function result(): Result {
        return $this->then();
    }

    public function then(callable $callback = null): Result {
        $carry = match(true) {
            $this->source !== null => ($this->source)(),
            default => $this->carry,
        };
        foreach ($this->processors as $processor) {
            $result = $processor($carry);
            if ($result->isFailure()) {
                foreach ($this->onFailure as $handler) {
                    $handler($result);
                }
                break;
            }
            $carry = $result;
        }
        return $this->applyThen($callback, $result);
    }

    private function applyThen(?callable $callback, Result $carry): Result {
        $result = match(true) {
            ($callback !== null) => $callback($carry),
            default => Result::with($carry),
        };
        return match(true) {
            $result instanceof Result => $result,
            ($result === null) => Result::failure("Callback returned null"),
            default => Result::success($result),
        };
    }
}