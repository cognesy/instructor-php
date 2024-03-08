<?php

namespace Cognesy\Instructor\Utils;

//////////////////////////////////////////////////////////
// Usage:
//
// class Person {
//     /** @return Optional<string> */
//     function getNameLength(?string $name): Optional {
//         return Optional::of($name)
//         ->apply(fn($n) => trim($n))
//         ->apply(fn($n) => strlen($n));
//     }
// }
//
// $nameLength = $person->getNameLength(null)->getOrElse(0);
//////////////////////////////////////////////////////////

 /**
 * @template T The type of the value in case of success.
 */
class Optional {
    private mixed $value;

    /**
     * @param T $value
     */
    private function __construct(mixed $value) {
        $this->value = $value;
    }

    /**
     * @param mixed $value
     * @return self<T>
     */
    public static function of(mixed $value): self {
        return new self($value);
    }

    public function exists(): bool {
        return $this->value !== null;
    }

    /**
     * @param T $default
     * @return T
     */
    public function getOrElse(mixed $default) : mixed {
        return $this->exists() ? $this->value : $default;
    }

    /**
     * @param callable $f
     * @return self<T>
     */
    public function apply(callable $f): self {
        if (!$this->exists()) {
            return self::of(null);
        }

        return self::of($f($this->value));
    }
}

