<?php

namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\Enums\TaskStatus;
use Exception;
use Throwable;

abstract class ExecutableTask extends Task
{
    protected Throwable $error;

    public function with(Signature $data) : mixed {
        try {
            $this->signature = $data;
            return $this->execute(...$this->inputs());
        } catch (Throwable $e) {
            $this->changeStatus(TaskStatus::Failed);
            $this->error = $e;
            $id = substr($this->id(), -4);
            print($e->getTraceAsString());
            throw new Exception("[...{$id}] {$this->name()} - task failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function withArgs(mixed ...$inputs) : mixed {
        try {
            $mapped = $this->mapFromInputs($inputs);
            $this->setInputs($mapped);
            return $this->execute(...$inputs);
        } catch (Throwable $e) {
            $this->changeStatus(TaskStatus::Failed);
            $this->error = $e;
            $id = substr($this->id(), -4);
            print($e->getTraceAsString());
            throw new Exception("[...{$id}] {$this->name()} - task failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function error() : Throwable {
        return $this->error;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    protected function execute(...$inputs) : mixed {
        $this->changeStatus(TaskStatus::InProgress);
        $result = $this->forward(...$inputs);
        $outputs = $this->mapToOutputs($result);
        $this->setOutputs($outputs);
        $this->changeStatus(TaskStatus::Completed);
        return $result;
    }

    protected function mapFromInputs(mixed $inputs) : array {
        $inputNames = $this->inputNames();
        $asArray = match(true) {
            ($inputs instanceof Signature) => $inputs->input()->getValues(),
            is_array($inputs) => $inputs,
            //(count($inputNames) === 1) => [$inputNames[0] => $inputs],
            is_object($inputs) && method_exists($inputs, 'toArray') => $inputs->toArray(),
            is_object($inputs) => get_object_vars($inputs),
            default => throw new Exception('Invalid inputs'),
        };
        return $this->mapFields($inputNames, $asArray);
    }

    protected function mapToOutputs(mixed $result) : array {
        $outputNames = $this->outputNames();
        $isSingleParamOutput = count($outputNames) === 1;
        $asArray = match(true) {
            $isSingleParamOutput => [$outputNames[0] => $result],
            is_array($result) => $result, // returned multiple params as array
            is_object($result) && method_exists($result, 'toArray') => $result->toArray(),
            is_object($result) => get_object_vars($result),
            default => $result,
        };
        return $this->mapFields($outputNames, $asArray);
    }

    /**
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
