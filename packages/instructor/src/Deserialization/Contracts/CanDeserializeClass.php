<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Can deserialize a JSON string into an object of given class.
 */
interface CanDeserializeClass
{
    /**
     * @template T of object
     * @param string $jsonData
     * @param class-string<T> $dataType
     * @return T
     */
    public function fromJson(string $jsonData, string $dataType) : mixed;
}