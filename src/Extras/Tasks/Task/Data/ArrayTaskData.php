<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Data;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\Contracts\CanHandleTaskData;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: 'Needs revision')]
class ArrayTaskData implements CanHandleTaskData
{
    private array $inputs = [];
    private array $outputs = [];

    static public function fromSignature(Signature $signature) : static {
        $data = new static();
        $data->inputs = self::makeFields($signature->getInputFields());
        $data->outputs = self::makeFields($signature->getOutputFields());
        return $data;
    }

    /** @return mixed[] */
    public function inputs(): array {
        return $this->inputs;
    }

    public function getInput(string $key): mixed {
        return $this->inputs[$key];
    }

    /** @param array<string, mixed> */
    public function setInputs(array $inputs): void {
        $this->validateInputs($inputs);
        foreach ($inputs as $key => $value) {
            $this->inputs[$key] = $value;
        }
    }

    /** @return mixed[] */
    public function outputs(): array {
        return $this->outputs;
    }

    public function getOutput(string $key): mixed {
        return $this->outputs[$key];
    }

    public function setOutputs(array $outputs): void {
        foreach ($outputs as $key => $value) {
            $this->outputs[$key] = $value;
        }
    }

    /** @param array<string, mixed> $inputs */
    private function validateInputs(array $inputs): void {
        $expected = $this->inputs;

        // check for missing inputs
        $missing = array_diff(array_keys($expected), array_keys($inputs));
        if (!empty($missing)) {
            throw new \InvalidArgumentException("Missing required input arguments: " . implode(', ', $missing));
        }

        // check for unexpected inputs
        $unexpected = array_diff(array_keys($inputs), array_keys($expected));
        if (!empty($unexpected)) {
            throw new \InvalidArgumentException("Unexpected input arguments: " . implode(', ', $unexpected));
        }
    }

    /** @param Field[] $args */
    static private function makeFields(array $args): array {
        $fields = [];
        foreach ($args as $arg) {
            $fields[$arg->name()] = null;
        }
        return $fields;
    }
}