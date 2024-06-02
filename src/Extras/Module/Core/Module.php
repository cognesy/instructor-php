<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Extras\Module\Core\Contracts\CanProcess;
use Cognesy\Instructor\Extras\Module\Core\Contracts\HasPendingExecution;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Task\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\Task\Enums\TaskStatus;
use Cognesy\Instructor\Extras\Module\Task\Task;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\TaskData\TaskDataFactory;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;
use Cognesy\Instructor\Utils\Result;
use Exception;


abstract class Module implements CanProcess
{
    use Traits\HandlesSignature;

    protected Signature $signature;

    public function with(HasInputOutputData $data) : HasPendingExecution {
        $task = $this->makeTask($data);
        $task->changeStatus(TaskStatus::Ready);
        return $this->makePendingExecution($task);
    }

    public function withArgs(mixed ...$inputs) : HasPendingExecution {
        $task = $this->makeTaskFromArgs(...$inputs);
        $task->changeStatus(TaskStatus::Ready);
        return $this->makePendingExecution($task);
    }

    public function process(CanBeProcessed $task) : mixed {
        try {
            $task->changeStatus(TaskStatus::InProgress);
            $result = $this->forward(...$task->data()->input()->getValues());
            $outputs = InputOutputMapper::toOutputs($result, $this->outputNames());
            $task->setOutputs($outputs);
            $task->changeStatus(TaskStatus::Completed);
        } catch (Exception $e) {
            $task->addError($e->getMessage(), ['exception' => $e]);
            $task->changeStatus(TaskStatus::Failed);
            throw $e;
        }
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    protected function makeTask(HasInputOutputData $data) : CanBeProcessed {
        return new Task($data);
    }

    protected function makeTaskFromArgs(mixed ...$inputs) : CanBeProcessed {
        $taskData = TaskDataFactory::fromSignature($this->getSignature());
        $taskData->withArgs(...$inputs);
        return $this->makeTask($taskData);
    }

    protected function inputNames() : array {
        return $this->getSignature()->inputNames();
    }

    protected function outputNames() : array {
        return $this->getSignature()->outputNames();
    }

    protected function makePendingExecution(CanBeProcessed $task) : HasPendingExecution {
        return new class($this, $task) implements HasPendingExecution {
            private CanProcess $module;
            private CanBeProcessed $task;
            private bool $executed = false;
            private mixed $result;

            public function __construct(CanProcess $module, CanBeProcessed $task) {
                $this->module = $module;
                $this->task = $task;
            }

            private function execute() : mixed {
                if ($this->executed) {
                    return $this->result;
                }
                $this->result = $this->module->process($this->task);
                $this->executed = true;
                return $this->result;
            }

            public function result() : mixed {
                return $this->execute();
            }

            public function try() : Result {
                try {
                    $data = $this->execute();
                    if ($this->task->hasErrors()) {
                        return Result::failure($this->task->errors());
                    }
                    return Result::success($data);
                } catch (Exception $e) {
                    return Result::failure($e);
                }
            }

            public function get(string $name = null) : mixed {
                $this->execute();
                if (empty($name)) {
                    return $this->task->outputs();
                }
                return $this->task->data()->output()->getPropertyValue($name);
            }

            public function hasErrors() : bool {
                return $this->task->hasErrors();
            }

            public function errors() : array {
                return $this->task->errors();
            }
        };
    }
}
