<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Messages\Messages;

/**
 * @implements CanProcessAnyState<object>
 */
final class AppendContextMetadata implements CanProcessAnyState
{
    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof HasMetadata
            && $state instanceof HasMessageStore
            && !$state->metadata()->isEmpty();
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof HasMetadata);
        // TODO: this should be done better (e.g. yaml vs json)
        $metadata = array_filter($newState->metadata()->toArray());
        if ($metadata === []) {
            return $newState;
        }

        $metadataString = "```json\n"
            . json_encode($metadata, JSON_PRETTY_PRINT)
            . "\n```";

        assert($newState instanceof HasMessageStore);
        $newMessages = $newState
            ->messages()
            ->appendMessages(Messages::fromString($metadataString));

        return $newState->withMessages($newMessages);
    }
}
