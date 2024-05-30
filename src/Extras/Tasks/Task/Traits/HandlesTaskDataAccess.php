<?php
namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

trait HandlesTaskDataAccess
{
    // CONVENIENCE METHODS ////////////////////////////////////////////////////

    /** @return array<string, mixed> */
    public function inputs(): array {
        return $this->getSignature()->input()->getValues();
    }

    /** @return array<string> */
    public function inputNames(): array {
        return $this->getSignature()->input()->getPropertyNames();
    }

    public function input(string $name) : mixed {
        return $this->getSignature()->input()->getPropertyValue($name);
    }

    /** @return array<string, mixed> */
    public function outputs(): array {
        return $this->getSignature()->output()->getValues();
    }

    /** @return array<string> */
    public function outputNames(): array {
        return $this->getSignature()->output()->getPropertyNames();
    }

    public function output(string $name) : mixed {
        return $this->getSignature()->output()->getPropertyValue($name);
    }

    /** @param array<string, mixed> $inputs */
    protected function setInputs(array $inputs): void {
        $this->getSignature()->input()->setValues($inputs);
    }

    /** @param array<string, mixed> $inputs */
    protected function setOutputs(array $outputs): void {
        $this->getSignature()->output()->setValues($outputs);
    }

    protected function outputRef() : mixed {
        return $this->getSignature()->output()->getRef();
    }
}
