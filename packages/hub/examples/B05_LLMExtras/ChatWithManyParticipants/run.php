---
title: 'Chat with many participants'
docname: 'chat_with_many_participants'
---

## Overview

## Example

```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Script;

// Human participant with callable for dynamic input
$human = new ExternalParticipant(name: 'user', messageProvider: function($state) {
    static $counter = 0;
    $inputs = [
        'Hello Dr. Chen and Marcus! I\'d love to hear both academic and industry perspectives on AI ethics.',
        'What do you each see as the biggest challenge in addressing AI bias - from your respective viewpoints?',
        'How do you balance innovation speed with ethical considerations in AI development?',
        'What role should regulation play in AI governance? I\'m curious about both academic and practical views.',
        'Thank you both for sharing your expertise!'
    ];
    return $inputs[$counter++] ?? 'No more input';
});

// Two assistants; Chat will normalize roles so the active one is "assistant"
$assistantA = new LLMParticipant(name: 'assistantA', model: 'gpt-4o-mini');
$assistantB = new LLMParticipant(name: 'assistantB', model: 'gpt-4o-mini');

$chat = new Chat(selector: new RoundRobinSelector());
$chat->withParticipants([$human, $assistantA, $assistantB]);

// Initialize the script sections that LLM participants expect

$script = new Script();
$script = $script->withSectionMessages('system', Messages::fromArray([
         ['role' => 'system', 'content' => 'You are participating in a multi-participant discussion about AI ethics.'],
         ['role' => 'assistant', 'name' => '', 'content' => 'AssistantA: You are Dr. Sarah Chen, an AI ethics researcher from MIT. You approach topics with academic rigor, cite research, and focus on policy implications. You tend to be cautious about AI deployment and emphasize the need for robust governance frameworks.'],
         ['role' => 'assistant', 'name' => '', 'content' => 'AssistantB: You are Marcus Thompson, a tech industry veteran and AI product manager. You bring practical experience from deploying AI systems at scale. You focus on real-world implementation challenges and business considerations while maintaining ethical standards.'],
     ]))
     ->withSection('summary')
     ->withSection('buffer')
     ->withSection('main');

$state = $chat->state()->withScript($script);
$chat->withState($state);

// Run conversation with human input from callable
foreach (range(1, 10) as $turn) {
    $step = $chat->nextTurn();
    $participantName = $step->participantName();
    $content = $step->messages()->toString();
    
    if ($participantName === 'user') {
        echo "Human: $content\n";
    } elseif ($participantName === 'assistantA') {
        echo "Dr. Sarah Chen (AI Ethics Researcher): $content\n";
    } elseif ($participantName === 'assistantB') {
        echo "Marcus Thompson (AI Product Manager): $content\n";
    } else {
        echo "AI ($participantName): $content\n";
    }
    
    if ($content === 'No more input') {
        break;
    }
}
?>
```
