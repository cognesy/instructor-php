<?php

namespace Cognesy\Instructor\Extras\Task;

use Cognesy\Instructor\Extras\Signature\Signature;
use Cognesy\Instructor\Extras\Task\Enums\TaskStatus;
use Throwable;

abstract class ExecutableTask extends Task
{
    protected Throwable $error;
    protected array $inputs;

    public function __construct(string|Signature $signature) {
        parent::__construct($signature);
    }

    public function with(array $inputs) : static {
        $this->inputs = $inputs;
        return $this;
    }

    protected function execute(array $inputs) : mixed {
        try {
            $this->changeStatus(TaskStatus::InProgress);
            $this->setInputs($inputs);
            $result = $this->forward(...$this->inputs());
            $outputs = match(true) {
                is_array($result) => $result,
                default => $this->scalarOutput($result),
            };
            $this->setOutputs($outputs);
            $this->changeStatus(TaskStatus::Completed);
        } catch (Throwable $e) {
            $this->changeStatus(TaskStatus::Failed);
            $this->error = $e;
            throw $e;
        }
        return $result;
    }

    public function get() : mixed {
        return $this->execute($this->inputs);
    }

    public function error() : Throwable {
        return $this->error;
    }
}
