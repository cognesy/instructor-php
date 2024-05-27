<?php

namespace Cognesy\Instructor\Extras\Signature;

use Cognesy\Instructor\Contracts\DataModel\CanHandleStructure;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ResponseModelSignature implements Signature
{
    protected string $signatureString;
    protected string $description;
    protected CanHandleStructure $inputs;
    protected CanHandleStructure $outputs;

    public function getDescription(): string {
        return $this->description;
    }

    public function getInputs(): CanHandleStructure {
        return $this->inputs;
    }

    public function getInputFields(): array {
    }

    public function asInputArgs(): array {
        // TODO: Implement asInputArgs() method.
    }

    public function getOutputs(): CanHandleStructure
    {
        // TODO: Implement getOutputs() method.
    }

    public function getOutputFields(): array
    {
        // TODO: Implement getOutputFields() method.
    }

    public function asOutputValues(): array
    {
        // TODO: Implement asOutputValues() method.
    }

    public function toString(): string
    {
        // TODO: Implement toString() method.
    }

    public function toDefaultPrompt(): string
    {
        // TODO: Implement toDefaultPrompt() method.
    }
}