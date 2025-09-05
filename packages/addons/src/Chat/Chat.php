<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\ChatObserver;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ContinuationCriteria;
use Cognesy\Addons\Chat\Data\StepProcessors;
use Cognesy\Addons\Chat\Events\ChatBeforeSend;
use Cognesy\Addons\Chat\Events\ChatCompleted;
use Cognesy\Addons\Chat\Events\ChatContextTransformed;
use Cognesy\Addons\Chat\Events\ChatParticipantSelected;
use Cognesy\Addons\Chat\Events\ChatTurnCompleted;
use Cognesy\Addons\Chat\Events\ChatTurnStarting;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Addons\Chat\Traits\Chat\HandlesContinuationCriteria;
use Cognesy\Addons\Chat\Traits\Chat\HandlesMessageProcessors;
use Cognesy\Addons\Chat\Traits\Chat\HandlesScriptProcessors;
use Cognesy\Addons\Chat\Traits\Chat\HandlesStepProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Messages\Messages;

class Chat
{
    use HandlesEvents;
    use HandlesContinuationCriteria;
    use HandlesStepProcessors;
    use HandlesScriptProcessors;
    use HandlesMessageProcessors;

    private ChatState $state;
    private CanChooseNextParticipant $selector;
    private StepProcessors $processors;
    private ContinuationCriteria $continuationCriteria;
    private ?ChatObserver $observer = null;

    public function __construct(
        ?ChatState $state = null,
        ?CanChooseNextParticipant $selector = null,
        ?array $processors = null,
        ?array $scriptProcessors = null,
        ?array $messageProcessors = null,
        ?array $continuationCriteria = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->state = $state ?? new ChatState();
        $this->selector = $selector ?? new RoundRobinSelector();
        $this->processors = new StepProcessors();
        if (!empty($processors)) { $this->withProcessors(...$processors); } else { $this->withDefaultProcessors(); }
        if (!empty($scriptProcessors)) { $this->withScriptProcessors(...$scriptProcessors); }
        if (!empty($messageProcessors)) { $this->withMessageProcessors(...$messageProcessors); } else {
            // sensible defaults: stamp participant metadata and normalize roles before send
            $this->withMessageProcessors(
                new \Cognesy\Addons\Chat\Processors\Messages\NormalizeRolesForActiveParticipant(),
                new \Cognesy\Addons\Chat\Processors\Messages\StampParticipantOnAppend(),
            );
        }
        $this->continuationCriteria = new ContinuationCriteria();
        if (!empty($continuationCriteria)) { $this->withContinuationCriteria(...$continuationCriteria); } else { $this->withDefaultContinuationCriteria(); }
    }

    // CONFIGURATION //////////////////////////////////////////////

    public static function new() : self { return new self(); }

    public function withState(ChatState $state) : self { $this->state = $state; return $this; }
    public function state() : ChatState { return $this->state; }

    public function withObserver(ChatObserver $observer) : self { $this->observer = $observer; return $this; }

    public function withSelector(CanChooseNextParticipant $selector) : self { $this->selector = $selector; return $this; }

    public function withParticipants(array $participants) : self { $this->state = $this->state->withParticipants(...$participants); return $this; }

    public function withMessages(string|array|Messages $messages, string $section = 'main') : self {
        $messages = match(true) {
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => Messages::fromArray($messages),
            default => $messages,
        };
        $script = $this->state->script()->withSectionMessages($section, $messages);
        $this->state = $this->state->withScript($script);
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////

    public function nextTurn() : ChatStep {
        $turn = $this->state->stepCount() + 1;
        $this->events->dispatch(new ChatTurnStarting([
            'state' => $this->state,
            'turn' => $turn,
        ]));
        if ($this->observer) { $this->observer->onStepStart($this->state); }

        $participant = $this->selector->choose($this->state);
        $this->events->dispatch(new ChatParticipantSelected([
            'state' => $this->state,
            'participant' => $participant,
            'participantId' => $participant?->id(),
            'participantClass' => $participant ? get_class($participant) : null,
        ]));
        if ($participant === null) {
            $this->events->dispatch(new ChatCompleted([
                'state' => $this->state,
                'reason' => 'no-participant',
            ]));
            return new ChatStep(participantId: 'none', messages: Messages::empty());
        }

        // Build prompt view and apply message pre-send processors
        $this->state = $this->state->withVariable('active_participant_id', $participant->id());
        $prompt = $this->state->script()->select(['system', 'summary', 'buffer', 'main'])->toMessages();
        $prompt = $this->applyBeforeSend($prompt, $this->state);
        $this->events->dispatch(new ChatBeforeSend([
            'state' => $this->state,
            'messages' => $prompt,
        ]));

        // Participant acts
        $step = $participant->act($this->state);

        // Apply message pre-append processors and append to script
        $messages = $this->applyBeforeAppend($step->messages(), $this->state);
        $before = $this->state->script();
        $existing = $before->section('main')->toMessages();
        $combined = $existing->appendMessages($messages);
        // Replace main section messages (do not append duplicates)
        $afterMain = $before->section('main')->withMessages($combined);
        $after = $before->replaceSection('main', $afterMain);
        $after = $this->applyScriptProcessors($after);
        $this->state = $this->state->withScript($after);
        $this->events->dispatch(new ChatContextTransformed([
            'state' => $this->state,
            'before' => $before,
            'after' => $after,
        ]));

        // Process step and update state
        $outcome = $this->processStep($step, $this->state);
        $step = $outcome->step();
        $this->state = $outcome->state();

        if ($this->observer) { $this->observer->onStepEnd($this->state, $step); }
        $this->events->dispatch(new ChatTurnCompleted([
            'state' => $this->state,
            'step' => $step,
        ]));
        if (!$this->hasNextTurn()) {
            $this->events->dispatch(new ChatCompleted([
                'state' => $this->state,
                'reason' => 'criteria-met',
            ]));
        }
        return $step;
    }

    public function finalTurn() : ChatStep {
        while ($this->hasNextTurn()) { $this->nextTurn(); }
        return $this->state->currentStep() ?? new ChatStep(participantId: 'none');
    }

    /** @return iterable<ChatStep> */
    public function iterator() : iterable {
        while ($this->hasNextTurn()) { yield $this->nextTurn(); }
    }
}
