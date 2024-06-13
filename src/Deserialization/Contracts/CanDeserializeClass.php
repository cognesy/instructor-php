<?php

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Can deserialize a JSON string into an object of given class.
 */
interface CanDeserializeClass
{
    /**
     * @template T
     * @param string $jsonData
     * @param class-string<T> $dataType
     * @return mixed<T>
     */
    public function fromJson(string $jsonData, string $dataType) : mixed;
}