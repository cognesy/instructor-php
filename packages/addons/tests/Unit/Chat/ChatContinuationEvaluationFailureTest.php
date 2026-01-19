<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\RoundRobin\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;

describe('Chat continuation evaluation failures', function () {
    it('records a failure outcome when continuation evaluation throws', function () {
        $participant = new class implements CanParticipateInChat {
            public function name(): string {
                return 'alpha';
            }

            public function act(ChatState $state): ChatStep {
                return new ChatStep(
                    participantName: 'alpha',
                    inputMessages: Messages::fromString('ping'),
                    outputMessages: Messages::fromString('pong'),
                );
            }
        };

        $criterion = ContinuationCriteria::when(
            static function (ChatState $state): ContinuationDecision {
                throw new \RuntimeException('criteria boom');
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $chat = new Chat(
            participants: new Participants($participant),
            nextParticipantSelector: new RoundRobinSelector(),
            processors: new StateProcessors(),
            continuationCriteria: $continuationCriteria,
            events: null,
            forceThrowOnFailure: false,
        );

        $state = (new ChatState())->withMessages(Messages::fromString('ping'));
        $failedState = $chat->nextStep($state);
        $outcome = $failedState->continuationOutcome();

        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->currentStep()?->errorsAsString())->toContain('criteria boom');
        expect($outcome)->not->toBeNull();
        expect($outcome?->stopReason())->toBe(StopReason::ErrorForbade);
        expect($outcome?->shouldContinue())->toBeFalse();
        expect($chat->hasNextStep($failedState))->toBeFalse();
    });
});
