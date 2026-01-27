<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Messages;

final class AppendContextMetadata implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return !$state->metadata()->isEmpty();
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        $metadata = array_filter($newState->metadata()->toArray());
        if ($metadata === []) {
            return $newState;
        }

        $metadataString = "```json\n"
            . json_encode($metadata, JSON_PRETTY_PRINT)
            . "\n```";

        $newMessages = $newState
            ->messages()
            ->appendMessages(Messages::fromString($metadataString));

        return $newState->withMessages($newMessages);
    }
}
