<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

/**
 * Response model can deserialize self from array data
 */
interface CanDeserializeSelf
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data, ?string $toolName = null) : static;
}
