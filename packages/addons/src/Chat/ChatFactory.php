<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\Chat\Data\Collections\ChatStateProcessors;
use Cognesy\Addons\Chat\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\Chat\Data\Collections\Participants;
use Cognesy\Addons\Chat\Processors\AccumulateTokenUsage;
use Cognesy\Addons\Chat\Processors\AppendStateMessages;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Events\Contracts\CanHandleEvents;

class ChatFactory {
    public static function default(
        Participants $participants,
        ?ContinuationCriteria $continuationCriteria = null,
        ?ChatStateProcessors $stepProcessors = null,
        ?CanHandleEvents $events = null,
    ): Chat {
        return new Chat(
            participants: $participants,
            nextParticipantSelector: new RoundRobinSelector(),
            stepProcessors: $stepProcessors ?? new ChatStateProcessors(
                new AppendStateMessages(),
                new AccumulateTokenUsage(),
            ),
            continuationCriteria: $continuationCriteria ?? new ContinuationCriteria(
                new FinishReasonCheck(),
                new StepsLimit(16),
                new TokenUsageLimit(4096),
            ),
            events: $events,
        );
    }
}