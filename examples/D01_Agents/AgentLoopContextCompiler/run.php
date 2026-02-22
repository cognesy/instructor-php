---
title: 'Custom Context Compiler'
docname: 'agent_loop_context_compiler'
order: 6
id: '893e'
---
## Overview

Context compilers control which messages the LLM sees at each step. By default,
`ConversationWithCurrentToolTrace` includes all conversation messages plus only
the current execution's tool traces. You can swap in a different compiler to
change what context the agent reasons over.

This example builds a custom compiler that wraps the default one and applies
two transformations:
- **Filtering**: Truncates long tool results so they don't overwhelm the context
- **Enrichment**: Injects a dynamic system message with execution progress info

The compiler logs what it does at each step, so you can see exactly what the
LLM receives.

Key concepts:
- `CanCompileMessages`: Interface for message compilers
- `ConversationWithCurrentToolTrace`: Default — includes conversation + current tool traces
- Custom compilers can filter, truncate, enrich, or transform the message list

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

$logger = new AgentEventConsoleObserver(
        useColors: true,
        showTimestamps: true,
        showContinuation: true,
        showToolArgs: true,
);

// Custom compiler that filters and enriches the context
class InstrumentedCompiler implements CanCompileMessages
{
    public function __construct(
        private readonly CanCompileMessages $inner,
        private readonly int $maxToolResultLength = 200,
    ) {}

    #[\Override]
    public function compile(AgentState $state): Messages
    {
        // 1. Get messages from the inner compiler
        $messages = $this->inner->compile($state);
        $originalCount = $messages->count();

        // 2. FILTER: truncate long tool results to keep context lean
        $messages = $messages->filter(function (Message $msg) {
            if ($msg->role()->value !== 'tool') {
                return true; // keep non-tool messages as-is
            }
            $content = $msg->content()->toString();
            if (strlen($content) <= $this->maxToolResultLength) {
                return true; // short enough, keep it
            }
            return true; // keep but we'll truncate below
        });

        // Apply truncation
        $truncated = [];
        $truncatedCount = 0;
        foreach ($messages->all() as $msg) {
            if ($msg->role()->value === 'tool') {
                $content = $msg->content()->toString();
                if (strlen($content) > $this->maxToolResultLength) {
                    $msg = new Message(
                        role: 'tool',
                        content: substr($content, 0, $this->maxToolResultLength) . '... [truncated]',
                        metadata: $msg->metadata(),
                    );
                    $truncatedCount++;
                }
            }
            $truncated[] = $msg;
        }
        $messages = Messages::fromMessages($truncated);

        // 3. ENRICH: inject execution context as a user instruction
        $step = $state->stepCount() + 1;
        $tokens = $state->usage()->total();
        $context = "[System note] You are on step {$step}. Tokens used so far: {$tokens}. Be concise.";
        $messages = $messages->appendMessage(
            new Message(role: 'user', content: $context)
        );

        // 4. LOG: show what the LLM will see
        echo "  [compiler] Compiled {$messages->count()} messages (from {$originalCount} original";
        if ($truncatedCount > 0) {
            echo ", {$truncatedCount} tool results truncated";
        }
        echo ")\n";
        foreach ($messages->all() as $msg) {
            $role = $msg->role()->value;
            $content = $msg->content()->toString();
            $len = strlen($content);
            if ($len === 0) {
                echo "    [{$role}] (tool calls only)\n";
            } else {
                $preview = substr(str_replace("\n", ' ', $content), 0, 72);
                echo "    [{$role}] ({$len}ch) {$preview}" . ($len > 72 ? '...' : '') . "\n";
            }
        }

        return $messages;
    }
}

// Wrap the default compiler with our instrumented one
$compiler = new InstrumentedCompiler(
    inner: new ConversationWithCurrentToolTrace(),
    maxToolResultLength: 200,
);

$agent = AgentLoop::default();
$agent = $agent
    ->withTool(BashTool::inDirectory(getcwd() ?: __DIR__))
    ->withDriver($agent->driver()->withMessageCompiler($compiler))
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withUserMessage(
    'List all files in the current directory, then tell me how many there are.'
);

echo "=== Agent with Custom Context Compiler ===\n\n";
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";

// Assertions
assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
?>
```
