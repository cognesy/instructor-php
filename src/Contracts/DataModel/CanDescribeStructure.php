<?php

namespace Cognesy\Instructor\Contracts\DataModel;

use Cognesy\Instructor\Schema\Data\TypeDetails;

interface CanDescribeStructure
{
    /** @return CanDescribeField[] */
    public function fields(): array;

    /** @return string[] */
    public function fieldNames(): array;

    public function has(string $name): bool;

    public function typeDetails(string $name): TypeDetails;

    public function field(string $name): CanHandleField;
}