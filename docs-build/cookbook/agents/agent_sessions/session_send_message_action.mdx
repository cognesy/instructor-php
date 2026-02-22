---
title: 'Session SendMessage Action'
docname: 'session_send_message_action'
order: 4
id: 'c1e7'
---
## Overview

Use `SendMessage` action to wake up the agent loop from persisted session state,
execute turns, and persist updated state between wake-ups.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$logger = new AgentEventConsoleObserver(useColors: true, showTimestamps: true, showContinuation: true);

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime-example'));

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'message-agent',
    description: 'Executes SendMessage action.',
    systemPrompt: 'You are a geography assistant. Answer in one short sentence.',
    llmConfig: 'openai',
)));

$sessionId = SessionId::from($created->sessionId());
$loopFactoryWithLogger = new class($loopFactory, $logger) implements \Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop {
    public function __construct(
        private readonly DefinitionLoopFactory $factory,
        private readonly AgentEventConsoleObserver $logger,
    ) {}

    public function instantiateAgentLoop(AgentDefinition $definition): \Cognesy\Agents\CanControlAgentLoop {
        echo "[runtime] Rebuilding agent loop from saved definition: {$definition->name}\n";
        return $this->factory->instantiateAgentLoop($definition)->wiretap($this->logger->wiretap());
    }
};

echo "=== Agent Execution Log ===\n\n";
echo "[runtime] Wake-up #1: loading persisted session {$sessionId->toString()}\n";
$beforeFirstWakeUp = $runtime->getSession($sessionId);
echo "[runtime] Wake-up #1: loaded version {$beforeFirstWakeUp->version()}, messages={$beforeFirstWakeUp->state()->messages()->count()}\n";
$afterFirstWakeUp = $runtime->execute(
    $sessionId,
    new SendMessage('What is the capital of France?', $loopFactoryWithLogger),
);

echo "[runtime] Wake-up #2: loading persisted session {$sessionId->toString()}\n";
$beforeSecondWakeUp = $runtime->getSession($sessionId);
echo "[runtime] Wake-up #2: loaded version {$beforeSecondWakeUp->version()}, messages={$beforeSecondWakeUp->state()->messages()->count()}\n";
$afterSecondWakeUp = $runtime->execute(
    $sessionId,
    new SendMessage('What is the closest major river to that city?', $loopFactoryWithLogger),
);

echo "\n=== Result ===\n";
echo 'Version after first wake-up: ' . $afterFirstWakeUp->version() . "\n";
echo 'Version after second wake-up: ' . $afterSecondWakeUp->version() . "\n";
echo 'Conversation messages count: ' . $afterSecondWakeUp->state()->messages()->count() . "\n";
echo 'Last response: ' . ($afterSecondWakeUp->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo "\nConversation transcript:\n";
echo $afterSecondWakeUp->state()->messages()->toString() . "\n";

assert($afterFirstWakeUp->version() === 2);
assert($afterSecondWakeUp->version() === 3);
assert($afterSecondWakeUp->state()->messages()->count() >= 2);
assert($afterSecondWakeUp->state()->messages()->toString() !== '');
assert(str_contains($afterSecondWakeUp->state()->messages()->toString(), 'What is the capital of France?'));
assert(str_contains($afterSecondWakeUp->state()->messages()->toString(), 'What is the closest major river to that city?'));
?>
```
