<?php

namespace Cognesy\Instructor\Extras\Task\Contracts;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;

interface CanHandleTaskData
{
    static public function fromSignature(Signature $signature) : static;
    public function inputs(): array;
    public function getInput(string $key): mixed;
    public function setInputs(array $inputs): void;
    /** @return string[] */
    public function outputs(): array;
    public function getOutput(string $key): mixed;
    public function setOutputs(array $outputs): void;
}
