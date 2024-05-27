<?php

namespace Cognesy\Instructor\Extras\Signature\Contracts;

use Cognesy\Instructor\Contracts\DataModel\CanHandleStructure;
use Cognesy\Instructor\Extras\Field\Field;

interface Signature
{
    public const ARROW = '->';

    public function getDescription() : string;

    public function getInputs(): CanHandleStructure;
    public function getInputFields(): array;
    public function asInputArgs(): array;
    /** @return Field[] */

    public function getOutputs(): \Cognesy\Instructor\Contracts\DataModel\CanHandleStructure;
    /** @return Field[] */
    public function getOutputFields(): array;
    public function asOutputValues(): array;

    public function toString() : string;
    public function toDefaultPrompt(): string;
}
