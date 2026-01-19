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

describe('Chat failure step results', function () {
    it('records a StepResult when a participant throws', function () {
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
            ->withMessages(Messages::fromString('ping'));
        $failedState = $chat->nextStep($state);

        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->lastStepResult())->not->toBeNull();
        expect($failedState->currentStep()?->errorsAsString())->toContain('participant boom');
        expect($chat->hasNextStep($failedState))->toBeFalse();
    });
});
