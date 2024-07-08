<?php
namespace Cognesy\Instructor\Extras\Module\Call\Traits;

/**
 * Convenience methods for accessing call data.
 */
trait HandlesCallDataAccess
{
    /** @return array<string, mixed> */
    public function inputs(): array {
        return $this->data()->input()->getValues();
    }

    /** @return array<string, mixed> */
    public function outputs(): array {
        return $this->data()->output()->getValues();
    }

    /** @param array<string, mixed> $inputs */
    public function setInputs(array $inputs): void {
        $this->data()->input()->setValues($inputs);
    }

    /** @param array<string, mixed> $inputs */
    public function setOutputs(array $outputs): void {
        $this->data()->output()->setValues($outputs);
    }

    /** @return array<string> */
    public function outputNames(): array {
        return $this->data()->signature()->toOutputSchema()->getPropertyNames();
    }

    /** @return array<string> */
    public function inputNames(): array {
        return $this->data()->signature()->toInputSchema()->getPropertyNames();
    }

    public function input(string $name) : mixed {
        return $this->data()->input()->getPropertyValue($name);
    }

    public function output(string $name) : mixed {
        return $this->data()->output()->getPropertyValue($name);
    }

    public function outputRef() : mixed {
        return $this->data()->output()->getDataRef();
    }
}
