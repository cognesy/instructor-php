<?php

namespace Cognesy\Instructor\Extras\Signature\Contracts;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataStructure;
use Cognesy\Instructor\Extras\Field\Field;

interface Signature
{
    public const ARROW = '->';

    public function getDescription() : string;

    public function getInputs(): CanHandleDataStructure;
    public function getInputFields(): array;
    public function asInputArgs(): array;
    /** @return Field[] */

    public function getOutputs(): CanHandleDataStructure;
    /** @return Field[] */
    public function getOutputFields(): array;
    public function asOutputValues(): array;

    public function toString() : string;
    public function toDefaultPrompt(): string;
}
