<?php

namespace Cognesy\Instructor\Extras\Tasks\TaskData\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface TaskData
{
    public function getInputSchema(string $name) : Schema;
    public function getInputValue(string $name) : mixed;
    public function setInputValue(string $name, mixed $value): void;
    public function setInputValues(array $values): void;

    public function getOutputSchema(string $name) : Schema;
    public function getOutputValue(string $name) : mixed;
    public function setOutputValue(string $name, mixed $value): void;
    public function setOutputValues(array $values): void;

    /** @return string[] */
    public function getInputNames(): array;

    /** @return string[] */
    public function getOutputNames(): array;

    /** @return array<string, mixed> */
    public function getInputValues(): array;

    /** @return array<string, mixed> */
    public function getOutputValues(): array;

    /** @return Schema[] */
    public function getInputSchemas(): array;

    /** @return Schema[] */
    public function getOutputSchemas(): array;

    public function getInputRef() : mixed;

    public function getOutputRef() : mixed;
}
