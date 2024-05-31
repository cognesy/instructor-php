<?php

namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Extras\Module\Core\Contracts\CanProcessInput;
use Cognesy\Instructor\Extras\Module\Core\Enums\TaskStatus;
use Exception;
use Throwable;

abstract class StatelessModule
{
    protected Throwable $error;

    abstract static public function signature() : string;

    public function with(Task $task) : mixed {
        try {
            return $this->execute($task);
        } catch (Throwable $e) {
            $task->changeStatus(TaskStatus::Failed);
            $this->error = $e;
            $id = substr($task->id(), -4);
            print($e->getTraceAsString());
            throw new Exception("[...{$id}] {$task->name()} - task failed: {$e->getMessage()}", 0, $e);
        }
    }

// CHANGE: withArgs() needs to be implemented at task level
// as task knows best how to map arbitrary inputs to its
// properties.
//
//    public function withArgs(mixed ...$inputs) : mixed {
//        try {
//            $mapped = $task->mapFromInputs($inputs);
//            $task->setInputs($mapped);
//            return $this->execute(...$inputs);
//        } catch (Throwable $e) {
//            $task->changeStatus(TaskStatus::Failed);
//            $this->error = $e;
//            $id = substr($task->id(), -4);
//            print($e->getTraceAsString());
//            throw new Exception("[...{$id}] {$task->name()} - task failed: {$e->getMessage()}", 0, $e);
//        }
//    }

    abstract protected function forward(mixed ...$args) : mixed;

    // at the Task level various data access methods are implemented
    // e.g.: raw(), asArray(), ...
    public function result() : Task {
    }

    public function error() : Throwable {
        return $this->error;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    protected function execute(CanProcessInput $task) : mixed {
        $task->changeStatus(TaskStatus::InProgress);
        $result = $task->forward(...$task->inputs());
        $outputs = $task->mapToOutputs($result);
        $task->setOutputs($outputs);
        $task->changeStatus(TaskStatus::Completed);
        return $result;
    }
}
