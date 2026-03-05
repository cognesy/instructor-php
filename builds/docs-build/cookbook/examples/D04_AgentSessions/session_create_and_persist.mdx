---
title: 'Session Create and Persist'
docname: 'session_create_and_persist'
order: 1
id: 'f93a'
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
$runtime = new SessionRuntime($repo, new EventDispatcher('session-create-and-persist-example'));

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$definition = new AgentDefinition(
    name: 'session-agent',
    description: 'Session persistence demo',
    systemPrompt: 'You are concise. Reply in one sentence.',
    llmConfig: 'openai',
);

$created = $repo->create($factory->create($definition));
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
?>
```
