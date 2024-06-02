<?php
namespace Cognesy\Instructor\Extras\Module\Utils;

use Cognesy\Instructor\Extras\Module\Task\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;
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
     * @param mixed $inputs
     * @param string[] $inputNames
     * @return array<string, mixed>
     */
    public function mapFromInputs(mixed $inputs, array $inputNames) : array {
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
     * @param mixed $result
     * @param string[] $outputNames
     * @return array<string, mixed>
     */
    public function mapToOutputs(mixed $result, array $outputNames) : array {
        $isSingleParamOutput = count($outputNames) === 1;
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
     * @param string[] $expectedFields
     * @param mixed[] $values
     * @return array<string, mixed>
     */
    private function mapFields(array $values, array $expectedFields) : array {
        $mapped = [];
        foreach ($expectedFields as $name) {
            if (!array_key_exists($name, $values)) {
                throw new Exception("Missing field: {$name} in " . json_encode($values));
            }
            $mapped[$name] = $values[$name];
        }
        return $mapped;
    }
}
