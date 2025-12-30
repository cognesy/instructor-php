<?php declare(strict_types=1);

namespace Cognesy\Utils\Context;

/**
 * Typed token for qualified service bindings.
 *
 * @template T of object
 */
final class Key
{
    /**
     * A unique identifier for the binding.
     */
    public string $id;

    /**
     * Expected FQCN of the service type.
     *
     * @var class-string<T>
     */
    public string $type;

    /**
     * @param string $id
     * @param class-string<T> $type
     */
    public function __construct(string $id, string $type) {
        $this->id = $id;
        $this->type = $type;
    }

    /**
     * @template U of object
     * @param string $id
     * @param class-string<U> $type
     * @return Key<U>
     */
    public static function of(string $id, string $type): self {
        return new self($id, $type);
    }
}
