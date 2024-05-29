<?php
namespace Cognesy\Instructor\Extras\Tasks\TaskData;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Exception;

class DualTaskData implements TaskData
{
    use Traits\HandlesObjectSchema;
    use Traits\HandlesObjectValues;

    private object $inputs;
    private object $outputs;
    /** @var string[] */
    private array $inputNames;
    /** @var string[] */
    private array $outputNames;

    public function __construct(
        object $inputs,
        object $outputs,
        array $inputNames = null,
        array $outputNames = null,
    ) {
        $this->inputs = $inputs;
        $this->outputs = $outputs;
        $this->inputNames = $inputNames ?? $this->getObjectSchema($this->inputs)->getPropertyNames();
        $this->outputNames = $outputNames ?? $this->getObjectSchema($this->outputs)->getPropertyNames();
    }

    public function getInputNames(): array {
        return $this->inputNames;
    }

    public function getInputSchema(string $name): Schema {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->getPropertySchema($this->inputs, $name);
    }

    public function getInputValue(string $name): mixed {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->inputs->$name;
    }

    public function setInputValue(string $name, mixed $value): void {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        $this->inputs->$name = $value;
    }

    public function getOutputNames(): array {
        return $this->outputNames;
    }

    public function getOutputSchema(string $name): Schema {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        return $this->getPropertySchema($this->outputs, $name);
    }

    public function getOutputValue(string $name): mixed {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        return $this->outputs->$name;
    }

    public function setOutputValue(string $name, mixed $value): void {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        $this->outputs->$name = $value;
    }

    /** @return array<string, mixed> */
    public function getInputValues() : array {
        return $this->getPropertyValues($this->inputs, $this->inputNames);
    }

    /** @return array<string, mixed> */
    public function getOutputValues() : array {
        return $this->getPropertyValues($this->outputs, $this->outputNames);
    }

    public function setInputValues(array $values): void {
        $this->setProperties($this->inputs, $this->inputNames, $values);
    }

    public function setOutputValues(array $values): void {
        $this->setProperties($this->outputs, $this->outputNames, $values);
    }

    /** @return Schema[] */
    public function getInputSchemas() : array {
        return $this->getPropertySchemas($this->inputs, $this->inputNames);
    }

    /** @return Schema[] */
    public function getOutputSchemas() : array {
        return $this->getPropertySchemas($this->outputs, $this->outputNames);
    }

    public function getInputRef() : object {
        return $this->inputs;
    }

    public function getOutputRef() : object {
        return $this->outputs;
    }
}
