<?php
namespace Cognesy\Instructor\Extras\Module\Utils;

use Cognesy\Instructor\Extras\Module\Call\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Utils\Json\Json;
use Exception;

class InputOutputMapper
{
    static public function fromInputs(mixed $inputs, array $inputNames) : array {
        return (new self)->mapFromInputs($inputs, $inputNames);
    }

    static public function toOutputs(mixed $result, array $outputNames) : array {
        return (new self)->mapToOutputs($result, $outputNames);
    }

    /**
     * Maps input values to the expected input fields.
     * @param mixed $inputs
     * @param string[] $inputNames
     * @return array<string, mixed>
     */
    public function mapFromInputs(mixed $inputs, array $inputNames) : array {
        // TODO: is there a way to consolidate value rendering?
        $asArray = match(true) {
            ($inputs instanceof CanBeProcessed) => $inputs->inputs(),
            ($inputs instanceof HasInputOutputData) => $inputs->input()->getValues(),
            is_array($inputs) => $inputs,
            //(count($inputNames) === 1) => [$inputNames[0] => $inputs],
            is_object($inputs) && method_exists($inputs, 'toArray') => $inputs->toArray(),
            is_object($inputs) => get_object_vars($inputs),
            default => throw new Exception('Invalid inputs'),
        };
        return $this->mapFields($asArray, $inputNames);
    }

    /**
     * Maps result values to the expected output fields.
     * @param mixed $result
     * @param string[] $outputNames
     * @return array<string, mixed>
     */
    public function mapToOutputs(mixed $result, array $outputNames) : array {
        $isSingleParamOutput = count($outputNames) === 1;
        // TODO: how to consolidate value/structure rendering?
        $asArray = match(true) {
            ($result instanceof CanBeProcessed) => $result->outputs(),
            ($result instanceof HasInputOutputData) => $result->output()->getValues(),
            $isSingleParamOutput => [$outputNames[0] => $result],
            is_array($result) => $result, // returned multiple params as array
            is_object($result) && method_exists($result, 'toArray') => $result->toArray(),
            is_object($result) => get_object_vars($result),
            default => $result,
        };
        return $this->mapFields($asArray, $outputNames);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    /**
     * Maps values to the expected fields.
     * @param string[] $expectedFields
     * @param mixed[] $values
     * @return array<string, mixed>
     */
    private function mapFields(array $values, array $expectedFields) : array {
        $mapped = [];
        foreach ($expectedFields as $name) {
            if (!array_key_exists($name, $values)) {
                throw new Exception("Missing field: {$name} in " . Json::encode($values) . ". Make sure to use names arguments if you're calling the module via withArgs().");
            }
            $mapped[$name] = $values[$name];
        }
        return $mapped;
    }
}
