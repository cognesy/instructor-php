---
title: 'Session Create and Persist'
docname: 'session_create_and_persist'
order: 1
id: 'f93a'
tags:
  - 'agent-sessions'
  - 'persistence'
  - 'storage'
---
## Overview

Create a session from `AgentDefinition`, run one `SendMessage` turn,
then persist and reload updated state.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-create-and-persist-example'));

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$definition = new AgentDefinition(
    name: 'session-agent',
    description: 'Session persistence demo',
    systemPrompt: 'You are concise. Reply in one sentence.',
    llmConfig: 'openai',
);

$created = $runtime->create($definition);
$sessionId = SessionId::from($created->sessionId());

$worked = $runtime->execute(
    $sessionId,
    new SendMessage('Explain in one sentence why persisted sessions are useful.', $loopFactory),
);

$loaded = $repo->load($sessionId);
$updated = $repo->save($worked->withState($worked->state()->withMetadata('phase', 'saved')));

echo "=== Result ===\n";
echo 'Session ID: ' . $created->sessionId() . "\n";
echo 'Version after create: ' . $created->version() . "\n";
echo 'Version after send message: ' . $worked->version() . "\n";
echo 'Version after save: ' . $updated->version() . "\n";
echo 'Last response: ' . ($worked->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo 'Metadata phase: ' . ($updated->state()->metadata()->get('phase') ?? 'missing') . "\n";
echo 'Loaded from store: ' . ($loaded !== null ? 'yes' : 'no') . "\n";

assert(!empty($created->sessionId()->toString()), 'Session ID should not be empty');
assert($created->version() >= 1, 'Created session should have version >= 1');
assert($worked->version() > $created->version(), 'Version should increment after SendMessage');
assert($updated->version() > $worked->version(), 'Version should increment after save');
assert(!empty($worked->state()->finalResponse()->toString()), 'SendMessage should produce a response');
assert($updated->state()->metadata()->get('phase') === 'saved', 'Metadata phase should be saved');
assert($loaded !== null, 'Session should be loadable from store');
?>
```
