<?php
namespace Cognesy\Instructor\Extras\Task\Traits;

use Cognesy\Instructor\Extras\Task\Contracts\CanHandleTaskData;

trait HandlesTaskData
{
    protected CanHandleTaskData $data;

    public function inputs(): array {
        return $this->data->inputs();
    }

    public function input(string $name) : mixed {
        return $this->data->getInput($name);
    }

    public function outputs(): array {
        return $this->data->outputs();
    }

    public function output(string $name) : mixed {
        return $this->data->getOutput($name);
    }

    protected function setInputs(array $inputs): void {
        $this->data->setInputs($inputs);
    }

    protected function setOutputs(array $outputs): void {
        $this->data->setOutputs($outputs);
    }

    protected function scalarOutput(mixed $value): array {
        $fields = $this->signature->getOutputFields();
        $name = array_keys($fields)[0];
        return [$name => $value];
    }
}
