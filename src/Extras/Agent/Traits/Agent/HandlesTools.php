<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Agent;

use Cognesy\Instructor\Extras\Agent\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\Agent\Task;
use Cognesy\Instructor\Extras\Agent\Tool;

trait HandlesTools
{
    private CanUseTools $tools;

    public function selectTool(Task $task) : ?Tool {
        return null;
    }

    public function useTool(Task $task, Tool $tool) : ?Task {
        return null;
    }
}
