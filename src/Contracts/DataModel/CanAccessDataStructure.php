<?php
namespace Cognesy\Instructor\Contracts\DataModel;

interface CanAccessDataStructure
{
    /** @return array<string, mixed> */
    public function fieldValues(): array;

    public function get(string $name): mixed;

    public function set(string $name, mixed $value): void;
}