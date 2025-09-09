<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Messages\Messages;

class AppendContextVariables implements CanProcessToolState
{
    public function process(ToolUseState $state, ?callable $next = null): ToolUseState {
        if (empty($state->variables())) {
            return $next ? $next($state) : $state;
        }
        
        $variables = array_filter($state->variables());
        $variablesStr = "```json\n" . json_encode($variables, JSON_PRETTY_PRINT) . "\n```";
        $newState = $state->appendMessages(Messages::fromString($variablesStr));
        
        return $next ? $next($newState) : $newState;
    }
}
