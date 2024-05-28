<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Contracts;

interface CanHandleTaskData
{
    static public function fromSignature(\Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature $signature) : static;
    public function inputs(): array;
    public function getInput(string $key): mixed;
    public function setInputs(array $inputs): void;
    /** @return string[] */
    public function outputs(): array;
    public function getOutput(string $key): mixed;
    public function setOutputs(array $outputs): void;
}
