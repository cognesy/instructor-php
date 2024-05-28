<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Agent;

use Cognesy\Instructor\Extras\Agent\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\Agent\Tool;
use Cognesy\Instructor\Extras\Tasks\Task\Task;

trait HandlesTools
{
    private CanUseTools $tools;

    public function selectTool(Task $task) : Tool {
    }

    public function useTool(Task $task, Tool $tool) : Task {
    }
}