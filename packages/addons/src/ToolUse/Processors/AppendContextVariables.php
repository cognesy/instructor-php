<?php

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;
use Cognesy\Utils\Messages\Messages;

class AppendContextVariables implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        if (empty($context->variables())) {
            return $step;
        }
        $variables = array_filter($context->variables());
        $variablesStr = "```json\n" . json_encode($variables, JSON_PRETTY_PRINT) . "\n```";
        $context->appendMessages(Messages::fromArray(['role' => 'user', 'content' => $variablesStr]));
        return $step;
    }
}
