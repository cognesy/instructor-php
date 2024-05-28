<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Contracts;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanHandleTaskData
{
    /** @return string[] */
    public function inputNames(): array;

    public function getInputValue(string $name): mixed;

    /** @return string[] */
    public function setInputValue(string $name, mixed $value): void;

    /** @return string[] */
    public function outputNames(): array;

    public function getOutputValue(string $name): mixed;

    /** @return string[] */
    public function setOutputValue(string $name, mixed $value): void;
}
