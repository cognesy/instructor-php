<?php
namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;

trait HandlesTaskData
{
    private TaskData $data;

    public function inputs(): array {
        return $this->data->getInputValues();
    }

    public function input(string $name) : mixed {
        return $this->data->getInputValue($name);
    }

    public function outputs(): array {
        return $this->data->getOutputValues();
    }

    public function output(string $name) : mixed {
        return $this->data->getOutputValue($name);
    }

    protected function setInputs(array $inputs): void {
        $this->data->setInputValues($inputs);
    }

    protected function setOutputs(array $outputs): void {
        $this->data->setOutputValues($outputs);
    }

    protected function scalarOutput(mixed $value): array {
        $fields = $this->data->getOutputNames();
        $name = $fields[0] ?? 'result';
        return [$name => $value];
    }
}
