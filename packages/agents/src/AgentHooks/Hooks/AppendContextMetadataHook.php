<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Messages\Messages;

/**
 * Hook that appends metadata as a JSON message after a step.
 *
 * Converts the agent state's metadata to a JSON-formatted message
 * and appends it to the conversation history.
 */
final readonly class AppendContextMetadataHook implements Hook
{
    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        if (!$context instanceof StepHookContext || $context->eventType() !== HookType::AfterStep) {
            return $next($context);
        }

        $state = $context->state();
        $metadata = array_filter($state->metadata()->toArray());

        if ($metadata === []) {
            return $next($context);
        }

        $metadataString = "```json\n"
            . json_encode($metadata, JSON_PRETTY_PRINT)
            . "\n```";

        $newMessages = $state
            ->messages()
            ->appendMessages(Messages::fromString($metadataString));

        $newState = $state->withMessages($newMessages);
        return $next($context->withState($newState));
    }
}
