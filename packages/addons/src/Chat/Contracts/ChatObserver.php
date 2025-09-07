<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Polyglot\Inference\PendingInference;

/**
 * Chat observer interface providing detailed visibility into chat execution.
 * Allows users to monitor and react to each step of the conversation.
 */
interface ChatObserver
{
    /**
     * Called before participant selection.
     * 
     * @param ChatState $state Current chat state
     */
    public function onTurnStart(ChatState $state): void;
    
    /**
     * Called after participant is selected but before execution.
     * 
     * @param ChatState $state Current chat state
     * @param CanParticipateInChat $participant Selected participant
     */
    public function onParticipantSelected(ChatState $state, CanParticipateInChat $participant): void;
    
    /**
     * Called when PendingInference is available (for LLM participants).
     * This is the hook for streaming access.
     * 
     * @param ChatState $state Current chat state
     * @param CanParticipateInChat $participant Current participant
     * @param PendingInference|null $pending Pending inference (null for non-LLM participants)
     */
    public function onInferenceReady(ChatState $state, CanParticipateInChat $participant, ?PendingInference $pending): void;
    
    /**
     * Called before executing the participant action.
     * 
     * @param ChatState $state Current chat state
     * @param CanParticipateInChat $participant Current participant
     */
    public function onBeforeExecution(ChatState $state, CanParticipateInChat $participant): void;
    
    /**
     * Called after participant execution is complete.
     * 
     * @param ChatState $state Current chat state
     * @param ChatStep $step Completed step
     */
    public function onStepComplete(ChatState $state, ChatStep $step): void;
    
    /**
     * Called when the conversation ends.
     * 
     * @param ChatState $state Final chat state
     * @param string $reason Reason for ending (e.g., 'criteria-met', 'no-participant', 'error')
     */
    public function onChatEnd(ChatState $state, string $reason): void;
}