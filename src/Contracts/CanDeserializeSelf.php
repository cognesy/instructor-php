<?php

namespace Cognesy\Instructor\Contracts;

/**
 * Response model can deserialize self from JSON data
 */
interface CanDeserializeSelf
{
    public function fromJson(string $jsonData) : static;
}