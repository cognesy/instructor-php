<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Exception;

trait HandlesArgMapping
{
    /**
     * Maps any format of results into a standardized input = array of key-value pairs
     *
     * @param mixed $inputs
     * @return array<string, mixed>
     */
    protected function mapFromInputs(mixed $inputs) : array {
        $inputNames = $this->inputNames();
        $asArray = match(true) {
            ($inputs instanceof HasSignature) => $inputs->input()->getValues(),
            is_array($inputs) => $inputs,
            //(count($inputNames) === 1) => [$inputNames[0] => $inputs],
            is_object($inputs) && method_exists($inputs, 'toArray') => $inputs->toArray(),
            is_object($inputs) => get_object_vars($inputs),
            default => throw new Exception('Invalid inputs'),
        };
        return $this->mapFields($inputNames, $asArray);
    }

    /**
     * Maps any format of result into a standardized output = array of key-value pairs
     *
     * @param mixed $result
     * @return array<string, mixed>
     */
    protected function mapToOutputs(mixed $result) : array {
        $outputNames = $this->outputNames();
        $isSingleParamOutput = count($outputNames) === 1;
        $asArray = match(true) {
            ($result instanceof HasSignature) => $result->output()->getValues(),
            $isSingleParamOutput => [$outputNames[0] => $result],
            is_array($result) => $result, // returned multiple params as array
            is_object($result) && method_exists($result, 'toArray') => $result->toArray(),
            is_object($result) => get_object_vars($result),
            default => $result,
        };
        return $this->mapFields($outputNames, $asArray);
    }

    /**
     * Maps arbitrary array of values into a standardized array of key-value pairs
     * based on the known / expected fields (as defined in the signature
     *
     * @param string[] $expectedFields
     * @param mixed[] $values
     * @return array<string, mixed>
     */
    private function mapFields(array $expectedFields, array $values) : array {
        $mapped = [];
        foreach ($expectedFields as $name) {
            if (!array_key_exists($name, $values)) {
                throw new Exception("Missing output field: {$name}");
            }
            $mapped[$name] = $values[$name];
        }
        return $mapped;
    }
}