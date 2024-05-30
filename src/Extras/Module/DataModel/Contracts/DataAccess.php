<?php

namespace Cognesy\Instructor\Extras\Module\DataModel\Contracts;

interface DataAccess
{
    public function getPropertyValue(string $name) : mixed;

    public function setPropertyValue(string $name, mixed $value): void;

    public function setValues(array $values): void;

    /** @return array<string, mixed> */
    public function getValues(): array;
}