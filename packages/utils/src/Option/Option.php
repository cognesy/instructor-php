<?php declare(strict_types=1);

namespace Cognesy\Utils\Option;

use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Option type representing an optional value.
 *
 * @template T
 */
abstract readonly class Option
{
    // FACTORIES ///////////////////////////////////////////////////////////////

    /**
     * @template U
     * @param U $value
     * @return Option<U>
     */
    public static function some(mixed $value): Option {
        return new Some($value);
    }

    /**
     * @return Option<never>
     */
    public static function none(): Option {
        return new None();
    }

    /**
     * @template U
     * @param U|null $value
     * @return Option<U>
     */
    public static function fromNullable(mixed $value): Option {
        /** @var Option<U> */
        return $value === null ? self::none() : self::some($value);
    }

    /**
     * Convert a Result into Option. Success(null) becomes None when $noneOnNull=true.
     * Failure becomes None.
     *
     * @template U
     * @param Result<U, mixed> $result
     * @param bool $noneOnNull
     * @return Option<U>
     * @phpstan-ignore-next-line
     */
    public static function fromResult(Result $result, bool $noneOnNull = true): Option {
        if (!$result->isSuccess()) {
            return self::none();
        }
        $value = $result->valueOr(null);
        if ($noneOnNull && $value === null) {
            return self::none();
        }
        return self::some($value);
    }

    // QUERIES //////////////////////////////////////////////////////////////////

    public function isSome(): bool {
        return $this instanceof Some;
    }

    public function isNone(): bool {
        return $this instanceof None;
    }

    /**
     * True if value exists and satisfies predicate.
     * @param callable(mixed):bool $predicate
     */
    public function exists(callable $predicate): bool {
        if ($this instanceof None) {
            return false;
        }
        return (bool) $predicate($this->getUnsafe());
    }

    /**
     * True if Option is None or value satisfies predicate.
     * @param callable(mixed):bool $predicate
     */
    public function forAll(callable $predicate): bool {
        if ($this instanceof None) {
            return true;
        }
        return (bool) $predicate($this->getUnsafe());
    }

    // TRANSFORMATIONS ///////////////////////////////////////////////////////////

    /**
     * Map value when present.
     * @template U
     * @param callable(T):U $f
     * @return Option<U>
     */
    public function map(callable $f): Option {
        if ($this instanceof None) {
            return $this;
        }
        $out = $f($this->getUnsafe());
        return self::some($out);
    }

    /**
     * Flat-map (bind) over Option.
     * @template U
     * @param callable(T):Option<U> $f
     * @return Option<U>
     */
    public function flatMap(callable $f): Option {
        if ($this instanceof None) {
            return $this;
        }
        $out = $f($this->getUnsafe());
        /** @phpstan-ignore-next-line */
        return $out instanceof Option ? $out : self::some($out);
    }

    /**
     * Alias for flatMap to mirror Result::then.
     * @template U
     * @param callable(T):Option<U> $f
     * @return Option<U>
     */
    public function andThen(callable $f): Option {
        return $this->flatMap($f);
    }

    /**
     * Keep Some only if predicate holds; otherwise None.
     * @param callable(T):bool $predicate
     * @return Option<T>
     */
    public function filter(callable $predicate): Option {
        if ($this instanceof None) {
            return $this;
        }
        return $predicate($this->getUnsafe()) ? $this : self::none();
    }

    /**
     * Combine two Options using a binary function; returns None if either is None.
     * @template U
     * @template R
     * @param Option<U> $other
     * @param callable(T,U):R $f
     * @return Option<R>
     */
    public function zipWith(Option $other, callable $f): Option {
        if ($this instanceof None) {
            return $this;
        }
        if ($other instanceof None) {
            return $other;
        }
        return self::some($f($this->getUnsafe(), $other->getUnsafe()));
    }

    // EFFECTS / OBSERVATION /////////////////////////////////////////////////////

    /**
     * Run callback when Some; returns self.
     * @param callable(T):void $f
     * @return Option<T>
     */
    public function ifSome(callable $f): Option {
        if ($this instanceof Some) {
            $f($this->getUnsafe());
        }
        return $this;
    }

    /**
     * Run callback when None; returns self.
     * @param callable():void $f
     * @return Option<T>
     */
    public function ifNone(callable $f): Option {
        if ($this instanceof None) {
            $f();
        }
        return $this;
    }

    // DESTRUCTURING /////////////////////////////////////////////////////////////

    /**
     * Pattern-match like destructuring.
     * @template R
     * @param callable():R $onNone
     * @param callable(T):R $onSome
     * @return R
     */
    public function match(callable $onNone, callable $onSome): mixed {
        return $this instanceof None
            ? $onNone()
            : $onSome($this->getUnsafe());
    }

    /**
     * Return contained value or default.
     * @param mixed|callable():mixed $default
     */
    public function getOrElse(mixed $default): mixed {
        if ($this instanceof Some) {
            return $this->getUnsafe();
        }
        return is_callable($default) ? $default() : $default;
    }

    /**
     * Return Option as-is, or a fallback Option.
     * @param Option<T>|callable():Option<T> $alternative
     * @return Option<T>
     */
    public function orElse(Option|callable $alternative): Option {
        if ($this instanceof Some) {
            return $this;
        }
        return is_callable($alternative) ? $alternative() : $alternative;
    }

    /**
     * Convert to nullable value.
     * @return T|null
     */
    public function toNullable(): mixed {
        return $this instanceof Some ? $this->getUnsafe() : null;
    }

    /**
     * Convert to Result; on None produce failure using Throwable or factory.
     * @param Throwable|callable():Throwable $onNone
     * @return Result
     */
    public function toResult(Throwable|callable $onNone): Result {
        if ($this instanceof Some) {
            return Result::success($this->getUnsafe());
        }
        $error = $onNone instanceof Throwable ? $onNone : $onNone();
        return Result::failure($error);
    }

    /**
     * Convert to Result by supplying a default value when None.
     * @param mixed|callable():mixed $default
     * @return Result
     */
    public function toSuccessOr(mixed $default): Result {
        $value = match(true) {
            $this instanceof Some => $this->getUnsafe(),
            is_callable($default) => $default(),
            default => $default,
        };
        return Result::success($value);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    /**
     * @return T
     */
    abstract protected function getUnsafe(): mixed;
}

