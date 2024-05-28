<?php

namespace Cognesy\Instructor\Extras\Tasks\TaskData\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface TaskData
{
    // INPUTS
    /** @return string[] */
    public function getInputNames(): array;
    public function getInputSchema(string $name) : Schema;
    public function getInputValue(string $name) : mixed;
    public function setInputValue(string $name, mixed $value): void;
    /** @return array<string, mixed> */
    public function getInputValues(): array;
    /** @return Schema[] */
    public function getInputSchemas(): array;

    // OUTPUTS
    /** @return string[] */
    public function getOutputNames(): array;
    public function getOutputSchema(string $name) : Schema;
    public function getOutputValue(string $name) : mixed;
    public function setOutputValue(string $name, mixed $value): void;
    /** @return array<string, mixed> */
    public function getOutputValues(): array;
    /** @return Schema[] */
    public function getOutputSchemas(): array;
}
