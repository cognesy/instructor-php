<?php

namespace Cognesy\Instructor\Extras\Task\Data;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Field\FieldFactory;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Task\Contracts\CanHandleTaskData;
use Cognesy\Instructor\Schema\Data\TypeDetails;

class StructureTaskData implements CanHandleTaskData
{
    private Structure $inputs;
    private Structure $outputs;

    static public function fromStructure(Structure $signature) : static {
        if (!$signature->has('inputs') || !$signature->has('outputs')) {
            throw new \InvalidArgumentException('Invalid structure, missing "inputs" or "outputs" fields');
        }
        $data = new static();
        $data->inputs = Structure::define('inputs', self::makeFields($signature->inputs));
        $data->outputs = Structure::define('outputs', self::makeFields($signature->outputs));
        return $data;
    }

    static public function fromSignature(Signature $signature) : static {
        $data = new static();
        $data->inputs = Structure::define('inputs', self::makeFields($signature->getInputFields()));
        $data->outputs = Structure::define('outputs', self::makeFields($signature->getOutputFields()));
        return $data;
    }

    public function inputs(): array {
        return $this->inputs->fieldValues();
    }

    public function getInput(string $key): mixed {
        return $this->inputs->get($key);
    }

    public function setInputs(array $inputs): void {
        $this->validateInputs($inputs);
        foreach ($inputs as $key => $value) {
            $this->inputs->set($key, $value);
        }
    }

    public function outputs(): array {
        return $this->outputs->fieldValues();
    }

    public function getOutput(string $key): mixed {
        return $this->outputs->get($key);
    }

    public function setOutputs(array $outputs): void {
        $count = count($outputs);
        if ($count !== $this->outputs->count()) {
            throw new \InvalidArgumentException('Invalid number of output values');
        }
        $fieldNames = $this->outputs->fieldNames();
        if ($count == 1) {
            $outputs = [$fieldNames[0] => $outputs];
        }
        $index = 0;
        foreach ($fieldNames as $key => $value) {
            $this->outputs->set($key, $value);
            $index++;
            // TODO: finish me
        }
    }

    static private function makeFields(array $args): array {
        $fields = [];
        foreach ($args as $arg) {
            $fields[] = FieldFactory::fromTypeName($arg->name(), $arg->typeDetails()->type);
        }
        return $fields;
    }

    private function validateInputs(array $inputs): void {
        $expected = $this->inputs;

        // check for missing inputs
        $missing = array_diff($expected->fieldNames(), array_keys($inputs));
        if (!empty($missing)) {
            throw new \InvalidArgumentException("Missing required input arguments: " . implode(', ', $missing));
        }

        // check for unexpected inputs
        $unexpected = array_diff(array_keys($inputs), $expected->fieldNames());
        if (!empty($unexpected)) {
            throw new \InvalidArgumentException("Unexpected input arguments: " . implode(', ', $unexpected));
        }

        // check for invalid input types
        foreach ($inputs as $key => $value) {
            $expectedType = $expected->field($key)->typeDetails()->type;
            $valueType = TypeDetails::getType($value);
            if ($valueType !== $expectedType) {
                throw new \InvalidArgumentException("Invalid input type for input argument '$key'. Expected $expectedType, got $valueType");
            }
        }
    }
}