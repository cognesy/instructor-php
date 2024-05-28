<?php

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Can deserialize a JSON string into an object of given class.
 */
interface CanDeserializeClass
{
    public function fromJson(string $jsonData, string $dataType) : mixed;
}