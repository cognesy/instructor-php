<?php

namespace Cognesy\Instructor\Extras\Agent\Traits;

use Cognesy\Instructor\Extras\Agent\Contracts\CanProcessTasks;
use Cognesy\Instructor\Extras\Task\Task;
use Cognesy\Instructor\Utils\Pipeline;

trait HandlesTasks
{
    private CanProcessTasks $taskProcessor;

    public function processTask(Task $task) : Task {
        return (new Pipeline)
            ->process($task)
            ->through([
                fn($task) => $this->preprocessTask($task),
                fn($task) => $this->taskProcessor->process($task),
                fn($task) => $this->postprocessTask($task),
            ])
            ->thenReturn();
    }

    protected function preprocessTask(Task $task) : Task {
        return $task;
    }

    protected function postprocessTask(Task $task) : Task {
        return $task;
    }
}