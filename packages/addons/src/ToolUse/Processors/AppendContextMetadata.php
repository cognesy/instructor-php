<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Messages\Messages;

class AppendContextMetadata implements CanProcessAnyState
{
    public function canProcess(object $state): bool {
        return $state instanceof ToolUseState;
    }

    public function process(object $state, ?callable $next = null): ToolUseState {
        if ($state->metadata()->isEmpty()) {
            return $next ? $next($state) : $state;
        }

        // TODO: this should be done better (e.g. yaml vs json)
        $metadata = array_filter($state->metadata()->toArray());
        $metadataString = "```json\n" . json_encode($metadata, JSON_PRETTY_PRINT) . "\n```";
        $newState = $state->appendMessages(Messages::fromString($metadataString));
        
        return $next ? $next($newState) : $newState;
    }
}
