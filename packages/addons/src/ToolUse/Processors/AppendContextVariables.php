<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolStep;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Messages\Messages;

class AppendContextVariables implements CanProcessToolStep
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseStep {
        if (empty($state->variables())) {
            return $step;
        }
        $variables = array_filter($state->variables());
        $variablesStr = "```json\n" . json_encode($variables, JSON_PRETTY_PRINT) . "\n```";
        $state->appendMessages(Messages::fromArray([[ 'role' => 'user', 'content' => $variablesStr ]]));
        return $step;
    }
}
