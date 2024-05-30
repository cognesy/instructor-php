<?php
namespace Cognesy\Instructor\Extras\Tasks\TaskData\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface DataModel
{
    /** @return string[] */
    public function getPropertyNames(): array;

    public function getRef() : mixed;

    // DATA ACCESS ////////////////////////////////////////////////////////////////

    public function getPropertyValue(string $name) : mixed;

    public function setPropertyValue(string $name, mixed $value): void;

    public function setValues(array $values): void;

    /** @return array<string, mixed> */
    public function getValues(): array;

    // SCHEMA ACCESS //////////////////////////////////////////////////////////////

    /** @return Schema[] */
    public function getPropertySchemas(): array;

    public function getPropertySchema(string $name) : Schema;

    public function toSchema() : Schema;
}
