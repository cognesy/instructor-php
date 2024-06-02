<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Agent;

use Cognesy\Instructor\Extras\Agent\Contracts\CanProcessTasks;
use Cognesy\Instructor\Extras\Module\Call\Call;
use Cognesy\Instructor\Utils\Pipeline;

trait HandlesTasks
{
    private CanProcessTasks $taskProcessor;

    public function processTask(Call $task) : Call {
        return (new Pipeline)
            ->process($task)
            ->through([
                fn($task) => $this->preprocessTask($task),
                fn($task) => $this->taskProcessor->process($task),
                fn($task) => $this->postprocessTask($task),
            ])
            ->thenReturn();
    }

    protected function preprocessTask(Call $task) : Call {
        return $task;
    }

    protected function postprocessTask(Call $task) : Call {
        return $task;
    }
}