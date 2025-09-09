<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Messages\Messages;

class AppendContextVariables implements CanProcessToolState
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseState {
        if (empty($state->variables())) {
            return $state;
        }
        $variables = array_filter($state->variables());
        $variablesStr = "```json\n" . json_encode($variables, JSON_PRETTY_PRINT) . "\n```";
        return $state->appendMessages(Messages::fromArray([[ 'role' => 'user', 'content' => $variablesStr ]]));
    }
}
