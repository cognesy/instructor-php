<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\RoundRobin\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;

final class CountingUsage extends Usage
{
    public function withAccumulated(Usage $usage): Usage {
        return new self(
            inputTokens: $this->inputTokens + 1,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
        );
    }
}

describe('Chat failure usage accumulation', function () {
    it('accumulates usage when onFailure records a step result', function () {
        $participant = new class implements CanParticipateInChat {
            public function name(): string {
                return 'boom';
            }

            public function act(ChatState $state): ChatStep {
                throw new \RuntimeException('participant boom');
            }
        };

        $chat = new Chat(
            participants: new Participants($participant),
            nextParticipantSelector: new RoundRobinSelector(),
            processors: new StateProcessors(),
            continuationCriteria: new ContinuationCriteria(),
            events: null,
            forceThrowOnFailure: false,
        );

        $state = (new ChatState())
            ->withMessages(Messages::fromString('ping'))
            ->withUsage(new CountingUsage());
        $failedState = $chat->nextStep($state);

        expect($failedState->usage()->input())->toBe(1);
    });
});
