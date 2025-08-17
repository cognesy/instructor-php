<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Response model can deserialize self from JSON data
 */
interface CanDeserializeSelf
{
    public function fromJson(string $jsonData, ?string $toolName = null) : static;
}