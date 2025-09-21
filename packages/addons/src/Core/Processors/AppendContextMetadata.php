<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\State\Contracts\HasMessageStore;
use Cognesy\Addons\Core\State\Contracts\HasMetadata;
use Cognesy\Messages\Messages;

final class AppendContextMetadata implements CanProcessAnyState
{
    public function canProcess(object $state): bool {
        return $state instanceof HasMetadata
            && $state instanceof HasMessageStore
            && !$state->metadata()->isEmpty();
    }

    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof HasMetadata);
        // TODO: this should be done better (e.g. yaml vs json)
        $metadata = array_filter($state->metadata()->toArray());
        if ($metadata === []) {
            return $next ? $next($state) : $state;
        }
        $metadataString = "```json\n" . json_encode($metadata, JSON_PRETTY_PRINT) . "\n```";

        assert($state instanceof HasMessageStore);
        $newMessages = $state->messages()->appendMessages(Messages::fromString($metadataString));
        $newState = $state->withMessages($newMessages);

        return $next ? $next($newState) : $newState;
    }
}
