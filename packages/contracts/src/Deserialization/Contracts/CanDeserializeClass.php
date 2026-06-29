<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Can deserialize an array into an object of given class.
 */
interface CanDeserializeClass
{
    /**
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $dataType
     * @return T
     */
    public function fromArray(array $data, string $dataType) : mixed;
}
