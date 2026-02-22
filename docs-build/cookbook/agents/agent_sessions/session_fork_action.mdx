---
title: 'Session Fork Action'
docname: 'session_fork_action'
order: 5
id: 'd7ab'
---
## Overview

Fork an existing session into a new one, then continue each branch independently.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Session\Actions\ForkSession;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);
$logger = new AgentEventConsoleObserver(useColors: true, showTimestamps: true, showContinuation: true);
$loopFactoryWithLogger = new class($loopFactory, $logger) implements CanInstantiateAgentLoop {
    public function __construct(
        private readonly DefinitionLoopFactory $factory,
        private readonly AgentEventConsoleObserver $logger,
    ) {}

    public function instantiateAgentLoop(AgentDefinition $definition): \Cognesy\Agents\CanControlAgentLoop {
        echo "[runtime] Rebuilding agent loop from saved definition: {$definition->name}\n";
        return $this->factory->instantiateAgentLoop($definition)->wiretap($this->logger->wiretap());
    }
};

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime-example'));

$parent = $repo->create($factory->create(new AgentDefinition(
    name: 'parent-agent',
    description: 'Travel planner session',
    systemPrompt: 'You are a travel planner. Answer in one short sentence.',
    llmConfig: 'openai',
)));
$parentId = SessionId::from($parent->sessionId());
echo "=== Agent Execution Log ===\n\n";
echo "[runtime] Seed parent session {$parentId->toString()}\n";
$parentWithContext = $runtime->execute(
    $parentId,
    new SendMessage('Suggest 3 attractions in Paris.', $loopFactoryWithLogger),
);

$forkedId = SessionId::from('forked-session-demo');
$forked = (new ForkSession($forkedId))->executeOn($parentWithContext);
$storedFork = $repo->create($forked);
echo "[runtime] Fork created {$storedFork->sessionId()} from parent {$parentId->toString()}\n";

$parentBranch = $runtime->execute(
    $parentId,
    new SendMessage('Now add one low-budget food recommendation.', $loopFactoryWithLogger),
);
$forkBranch = $runtime->execute(
    $forkedId,
    new SendMessage('Now add one luxury dining recommendation.', $loopFactoryWithLogger),
);

echo "\n=== Result ===\n";
echo 'Parent session: ' . $parentId->toString() . "\n";
echo 'Forked session: ' . $storedFork->sessionId() . "\n";
echo 'Fork parent ID: ' . ($storedFork->info()->parentId() ?? 'none') . "\n";
echo "\nParent branch last response:\n";
echo ($parentBranch->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo "\nFork branch last response:\n";
echo ($forkBranch->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo "\nParent transcript:\n";
echo $parentBranch->state()->messages()->toString() . "\n";
echo "\nFork transcript:\n";
echo $forkBranch->state()->messages()->toString() . "\n";

assert($storedFork->sessionId() === 'forked-session-demo');
assert($storedFork->info()->parentId() === $parentId->toString());
assert($parentBranch->state()->messages()->count() >= 4);
assert($forkBranch->state()->messages()->count() >= 4);
?>
```
