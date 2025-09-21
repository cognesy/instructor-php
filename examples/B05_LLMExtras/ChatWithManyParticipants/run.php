---
title: 'Multi-Participant AI Chat Panel Discussion'
docname: 'chat_with_many_participants'
---

## Overview

This example demonstrates a sophisticated multi-participant chat system featuring:
- **System prompt isolation** - each AI participant has their own persona
- **Role normalization** - proper LLM role mapping for multi-participant conversations
- **AI-powered moderation** - LLM coordinator decides who should speak next based on context
- **Clean state management** - everything configured in immutable ChatState
- **Type-safe participant selection** - StructuredOutput for decision making

## Example

```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Addons\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

echo "🎙️ AI PANEL DISCUSSION: The Future of AI Development\n";
echo "═══════════════════════════════════════════════════\n\n";

// Create participants with distinct personas using system prompts

$moderator = new ScriptedParticipant(
    name: 'moderator',
    messages: [
        "Welcome to our panel! Could you each introduce yourselves and share your main focus area in AI?",
        "What do you see as the biggest challenge in AI adoption today?",
        "How do you balance rapid innovation with responsible deployment?",
        "What role should public funding play in AI research and development?",
        "Any final thoughts for our audience about the future of AI?",
        "", // Empty string to signal end of discussion
    ],
);

$researcher = new LLMParticipant(
    name: 'dr_chen',
    llmProvider: LLMProvider::using('openai'),
    systemPrompt: 'You are Dr. Sarah Chen, a distinguished AI researcher at MIT focusing on machine reasoning and safety. You participate in a panel with other experts and a moderator. You speak from deep academic knowledge, cite research when relevant, and always consider long-term implications. Keep responses concise but insightful - 2-3 sentences max. Always end with your signature: "- Dr. Chen"'
);

$engineer = new LLMParticipant(
    name: 'marcus',
    llmProvider: LLMProvider::using('openai'),
    systemPrompt: 'You are Marcus Rodriguez, a Senior AI Engineer at a major tech company with 10+ years building production AI systems. You participate in a panel with other experts and a moderator. You focus on practical implementation, scalability, and real-world challenges. Keep responses brief and pragmatic - 2-3 sentences max. Always end with: "- Marcus"'
);

// Run the panel discussion
$chat = ChatFactory::default(
    participants: new Participants($moderator, $engineer, $researcher),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(15, fn(ChatState $state): int => $state->stepCount()),
        new ResponseContentCheck(
            fn(ChatState $state): ?Messages => $state->currentStep()?->outputMessages(),
            static fn(Messages $lastResponse): bool => $lastResponse->last()->content()->toString() !== '',
        ),
    ),
); //->wiretap(fn(Event $e) => $e->print());

$participantNames = [
    'moderator' => '🎙️ Moderator',
    'dr_chen' => '🔬 Dr. Chen',
    'marcus' => '⚙️ Marcus',
];

$state = new ChatState();

while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();

    if ($step) {
        $participantName = $step->participantName();
        $content = trim($step->outputMessages()->toString());
        
        // Only display if there's actual content
        if (!empty($content)) {
            $displayName = $participantNames[$participantName] ?? "🤖 $participantName";
            echo "\n$displayName:\n";
            echo str_repeat('-', strlen($displayName)) . "\n";
            echo "$content\n\n";
        }
    }
}
echo "🎬 Panel discussion concluded!\n";
?>
```
