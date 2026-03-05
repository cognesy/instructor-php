---
title: 'Session Conflict Handling'
docname: 'session_conflict_handling'
order: 6
id: 'e5f1'
---
## Overview

Conflicts are explicit exceptions. This example runs one work turn,
then simulates a stale write conflict.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-conflict-example'));

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'conflict-agent',
    description: 'Conflict demo',
    systemPrompt: 'You are helpful. Reply in one sentence.',
    llmConfig: 'openai',
)));

$sessionId = SessionId::from($created->sessionId());
$worked = $runtime->execute(
    $sessionId,
    new SendMessage('Do one short task before conflict simulation.', $loopFactory),
);

$copyA = $repo->load($sessionId);
$copyB = $repo->load($sessionId);

if ($copyA === null || $copyB === null) {
    throw new RuntimeException('Expected both copies to be loaded');
}

$repo->save($copyA->withState($copyA->state()->withMetadata('writer', 'A')));

echo "=== Result ===\n";
echo 'Version after work turn: ' . $worked->version() . "\n";
echo 'Work response: ' . ($worked->state()->finalResponse()->toString() ?: 'No response') . "\n";

try {
    $repo->save($copyB->withState($copyB->state()->withMetadata('writer', 'B')));
    echo "Unexpected: no conflict raised\n";
} catch (SessionConflictException $e) {
    echo 'Conflict detected as expected: ' . $e->getMessage() . "\n";
}
?>
```
