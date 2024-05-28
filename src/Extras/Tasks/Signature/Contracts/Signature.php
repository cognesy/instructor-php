<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface Signature
{
    public const ARROW = '->';

    public function getDescription() : string;
    public function toString() : string;
    public function toDefaultPrompt(): string; // TODO: this does not belong here

    public function getInputFields(): array;
    public function getInputSchema(): Schema;

    /** @return array<string, mixed> */
    public function getInputValues(): array;

    public function getOutputSchema(): Schema;
    public function getOutputFields(): array;
}
