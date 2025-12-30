<?php declare(strict_types=1);

namespace Cognesy\Utils\Context\Psr;

use Cognesy\Utils\Context\Key;
use Psr\Container\ContainerInterface;
use TypeError;

/**
 * Typed facade over any PSR-11 container.
 */
final class TypedContainer
{
    public function __construct(private ContainerInterface $inner) {}

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class): object {
        $value = $this->inner->get($class);
        if (!$value instanceof $class) {
            throw new TypeError("Expected {$class}, got " . get_debug_type($value));
        }
        return $value;
    }

    /**
     * @template T of object
     * @param Key<T> $key
     * @return T
     */
    public function getKey(Key $key): object {
        $value = $this->inner->get($key->id);
        if (!$value instanceof $key->type) {
            throw new TypeError("Expected {$key->type} for {$key->id}, got " . get_debug_type($value));
        }
        return $value;
    }
}

