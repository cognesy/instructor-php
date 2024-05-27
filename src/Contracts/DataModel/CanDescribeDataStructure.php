<?php

namespace Cognesy\Instructor\Contracts\DataModel;

use Cognesy\Instructor\Schema\Data\TypeDetails;

interface CanDescribeDataStructure
{
    public function name(): string;
    public function description(): string;

    /** @return CanDescribeDataField[] */
    public function fields(): array;

    /** @return string[] */
    public function fieldNames(): array;

    public function has(string $name): bool;

    public function typeDetails(string $name): TypeDetails;

    public function field(string $name): CanHandleDataField;
}