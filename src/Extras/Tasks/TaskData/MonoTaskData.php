<?php
namespace Cognesy\Instructor\Extras\Tasks\TaskData;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Exception;

class MonoTaskData implements TaskData
{
    use Traits\HandlesObjectSchema;
    use Traits\HandlesObjectValues;

    private object $data;
    /** @var string[] */
    private array $inputNames;
    /** @var string[] */
    private array $outputNames;

    /**
     * @param object $data
     * @param string[] $inputNames
     * @param string[] $outputNames
     */
    public function __construct(
        object $data,
        array $inputNames,
        array $outputNames
    ) {
        $this->data = $data;
        $this->inputNames = $inputNames;
        $this->outputNames = $outputNames;
    }

    /** @return string[] */
    public function getInputNames(): array {
        return $this->inputNames;
    }

    public function getInputSchema(string $name): Schema {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->getPropertySchema($this->data, $name);
    }

    public function getInputValue(string $name): mixed {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->data->$name;
    }

    public function setInputValue(string $name, mixed $value): void {
        if (!in_array($name, $this->inputNames)) {
            throw new Exception("No input field '$name'");
        }
        $this->data->$name = $value;
    }

    /** @return string[] */
    public function getOutputNames(): array {
        return $this->outputNames;
    }

    public function getOutputSchema(string $name): Schema {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        return $this->getPropertySchema($this->data, $name);
    }

    public function getOutputValue(string $name): mixed {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        return $this->data->$name;
    }

    public function setOutputValue(string $name, mixed $value): void {
        if (!in_array($name, $this->outputNames)) {
            throw new Exception("No output field '$name'");
        }
        $this->data->$name = $value;
    }

    /** @return array<string, mixed> */
    public function getInputValues() : array {
        return $this->getPropertyValues($this->data, $this->inputNames);
    }

    /** @return array<string, mixed> */
    public function getOutputValues() : array {
        return $this->getPropertyValues($this->data, $this->outputNames);
    }

    /** @return Schema[] */
    public function getInputSchemas() : array {
        return $this->getPropertySchemas($this->data, $this->inputNames);
    }

    /** @return Schema[] */
    public function getOutputSchemas() : array {
        return $this->getPropertySchemas($this->data, $this->outputNames);
    }

    public function setInputValues(array $values): void {
        $this->setProperties($this->data, $this->inputNames, $values);
    }

    public function setOutputValues(array $values): void {
        $this->setProperties($this->data, $this->outputNames, $values);
    }

    public function getInputRef() : object {
        return $this->data;
    }

    public function getOutputRef() : object {
        return $this->data;
    }
}
