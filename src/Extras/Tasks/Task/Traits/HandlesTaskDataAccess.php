<?php
namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;

trait HandlesTaskDataAccess
{
    public function data(): TaskData {
        return $this->getSignature()->data();
    }

    // CONVENIENCE METHODS ////////////////////////////////////////////////////

    /** @return array<string, mixed> */
    public function inputs(): array {
        return $this->data()->getInputValues();
    }

    public function input(string $name) : mixed {
        return $this->data()->getInputValue($name);
    }

    /** @return array<string, mixed> */
    public function outputs(): array {
        return $this->data()->getOutputValues();
    }

    public function output(string $name) : mixed {
        return $this->data()->getOutputValue($name);
    }

    /** @param array<string, mixed> $inputs */
    protected function setInputs(array $inputs): void {
        $this->data()->setInputValues($inputs);
    }

    /** @param array<string, mixed> $inputs */
    protected function setOutputs(array $outputs): void {
        $this->data()->setOutputValues($outputs);
    }
}
