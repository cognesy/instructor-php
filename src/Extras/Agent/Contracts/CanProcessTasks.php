<?php

namespace Cognesy\Instructor\Extras\Agent\Contracts;

use Cognesy\Instructor\Extras\Tasks\Task\Task;

interface CanProcessTasks
{
    public function process(Task $task) : Task;
}