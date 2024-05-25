<?php

namespace Cognesy\Instructor\Extras\Agent\Traits;

use Cognesy\Instructor\Extras\Agent\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\Task\Task;
use Cognesy\Instructor\Extras\Tool\Tool;

trait HandlesTools
{
    private CanUseTools $tools;

    public function selectTool(Task $task) : Tool {
    }

    public function useTool(Task $task, Tool $tool) : Task {
    }
}