<?php
namespace Cognesy\Instructor\Extras\Module\Core\Traits\OperationCall;

trait HandlesInputs
{
    public function inputs() : array {
        return $this->inputs;
    }

    public function hasInput(string $name) : bool {
        return isset($this->inputs[$name]);
    }

    public function inputFields() : array {
        return array_keys($this->inputs());
    }
}