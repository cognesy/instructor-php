---
title: 'SessionRuntime Execute Action'
docname: 'session_runtime_execute_action'
order: 2
id: 'a6ce'
---
## Overview

Run one `SendMessage` turn, then lifecycle actions through `SessionRuntime::execute()`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Session\Actions\ResumeSession;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Actions\SuspendSession;
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
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime-example'));

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'runtime-agent',
    description: 'Runtime action demo',
    systemPrompt: 'You are helpful. Reply in one short sentence.',
    llmConfig: 'openai',
)));

$sessionId = SessionId::from($created->sessionId());
$worked = $runtime->execute($sessionId, new SendMessage('Confirm that one work turn was executed.', $loopFactory));
$suspended = $runtime->execute($sessionId, new SuspendSession());
$resumed = $runtime->execute($sessionId, new ResumeSession());

echo "=== Result ===\n";
echo 'Initial status: ' . $created->status()->value . "\n";
echo 'After work turn response: ' . ($worked->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo 'After suspend: ' . $suspended->status()->value . "\n";
echo 'After resume: ' . $resumed->status()->value . "\n";
echo 'Current version: ' . $resumed->version() . "\n";
?>
```
