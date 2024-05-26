<?php

namespace Cognesy\Instructor\Extras\Signature\Contracts;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

interface Signature
{
    public const ARROW = '->';

    public function getDescription() : string;

    public function getInputs(): Structure;
    public function getInputFields(): array;
    public function asInputArgs(): array;
    /** @return Field[] */

    public function getOutputs(): Structure;
    /** @return Field[] */
    public function getOutputFields(): array;
    public function asOutputValues(): array;

    public function toString() : string;
    public function toDefaultPrompt(): string;
}
