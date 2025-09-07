---
title: 'Streaming Chat with Fine-Grained Control'
docname: 'streaming_chat_control'
---

## Overview

This example demonstrates the new TransparentChat system that provides:
- **PendingInference access** - Direct streaming control over LLM responses
- **ChatObserver pattern** - Deep visibility into chat execution steps  
- **Driver abstraction** - Pluggable chat execution strategies
- **Fine-grained control** - No more black box execution

## Example

```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Contracts\ChatObserver;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Processors\AccumulateTokenUsage;
use Cognesy\Addons\Chat\Processors\AddCurrentStep;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Addons\Chat\TransparentChat;
use Cognesy\Messages\Script\Script;
use Cognesy\Polyglot\Inference\PendingInference;

echo "🔄 STREAMING CHAT WITH FINE-GRAINED CONTROL\n";
echo "════════════════════════════════════════════\n\n";

// Create a custom observer for detailed monitoring
class StreamingChatObserver implements ChatObserver
{
    public function onTurnStart(ChatState $state): void
    {
        echo "🎯 Turn " . ($state->stepCount() + 1) . " starting...\n";
    }
    
    public function onParticipantSelected(ChatState $state, CanParticipateInChat $participant): void
    {
        echo "👤 Selected participant: {$participant->name()}\n";
    }
    
    public function onInferenceReady(ChatState $state, CanParticipateInChat $participant, ?PendingInference $pending): void
    {
        if ($pending && $participant instanceof LLMParticipant) {
            echo "🧠 LLM inference ready - streaming available!\n";
            echo "📡 Starting streamed response...\n";
            
            // STREAMING ACCESS - This is the key capability!
            foreach ($pending->stream() as $chunk) {
                if ($chunk->delta->content) {
                    echo $chunk->delta->content;
                    usleep(50000); // 50ms delay for visual effect
                }
            }
            echo "\n📡 Stream completed\n";
        } else {
            echo "💬 Non-LLM participant - no streaming\n";
        }
    }
    
    public function onBeforeExecution(ChatState $state, CanParticipateInChat $participant): void
    {
        echo "⚡ Executing {$participant->name()} action...\n";
    }
    
    public function onStepComplete(ChatState $state, ChatStep $step): void
    {
        $usage = $step->usage();
        echo "✅ Step completed for {$step->participantName()}";
        if ($usage) {
            echo " (tokens: {$usage->inputTokens}/{$usage->outputTokens})";
        }
        echo "\n";
    }
    
    public function onChatEnd(ChatState $state, string $reason): void
    {
        echo "🏁 Chat ended: $reason\n";
        echo "📊 Total steps: {$state->stepCount()}\n\n";
    }
}

// Create participants
$user = new ExternalParticipant(
    name: 'user',
    messageProvider: function() {
        static $questions = [
            'Hello! Can you explain quantum computing?',
            'What are the practical applications?',
            'Thanks for the explanation!',
        ];
        static $index = 0;
        
        if ($index >= count($questions)) {
            return '';
        }
        
        return $questions[$index++];
    }
);

$assistant = new LLMParticipant(
    name: 'assistant',
    model: 'gpt-4o-mini',
    systemPrompt: 'You are a helpful AI assistant. Keep responses concise but informative - 2-3 sentences max.'
);

// Set up chat state with transparency
$script = (new Script())
    ->withSection('system')
    ->withSection('summary')
    ->withSection('buffer')
    ->withSection('main');

$state = (new ChatState($script))
    ->withParticipants($user, $assistant)
    ->withNextParticipantSelector(new RoundRobinSelector())
    ->withContinuationCriteria((new ContinuationCriteria())->add(new StepsLimit(6)))
    ->withStepProcessors([
        new AccumulateTokenUsage(),
        new AddCurrentStep(),
    ]);

// Create transparent chat with observer
$chat = (new TransparentChat())
    ->withState($state)
    ->withObserver(new StreamingChatObserver());

echo "Starting transparent chat with streaming control...\n\n";

// Method 1: Manual step-by-step control
echo "🎮 MANUAL CONTROL MODE:\n";
echo "═══════════════════════\n";

$stepCount = 0;
while ($chat->hasNextTurn() && $stepCount < 3) {
    echo "\n--- Step " . (++$stepCount) . " ---\n";
    
    // Get access to internal state before execution
    echo "📋 Current state: {$chat->state()->stepCount()} steps\n";
    
    // Execute one turn with full transparency
    $newState = $chat->nextTurn();
    
    // Access the driver for advanced control
    $driver = $chat->driver();
    echo "🔧 Driver type: " . get_class($driver) . "\n";
    
    echo "\n";
}

echo "\n🔄 ITERATOR MODE (remaining steps):\n";
echo "═══════════════════════════════════\n";

// Method 2: Iterator pattern for streaming through remaining steps
foreach ($chat->iterator() as $step) {
    echo "📝 Step yielded: {$step->participantName()}\n";
    if ($step->messages()->notEmpty()) {
        echo "💬 Content: " . trim($step->messages()->toString()) . "\n";
    }
    echo "\n";
}

echo "💡 This example demonstrates:\n";
echo "   • Direct access to PendingInference for streaming\n";
echo "   • ChatObserver pattern for detailed execution visibility\n"; 
echo "   • Driver abstraction for pluggable execution strategies\n";
echo "   • Fine-grained control over each chat step\n";
echo "   • No more black box - full transparency!\n";

?>
```