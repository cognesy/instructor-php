<?php

namespace Cognesy\Instructor\Extras\ToolUse\Processors;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanProcessStep;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Utils\Messages\Messages;

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
