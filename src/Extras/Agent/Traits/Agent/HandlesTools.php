<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Agent;

use Cognesy\Instructor\Extras\Agent\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\Agent\Tool;
use Cognesy\Instructor\Extras\Module\Call\Call;

trait HandlesTools
{
    private CanUseTools $tools;

    public function selectTool(Call $task) : ?Tool {
        return null;
    }

    public function useTool(Call $task, Tool $tool) : ?Call {
        return null;
    }
}