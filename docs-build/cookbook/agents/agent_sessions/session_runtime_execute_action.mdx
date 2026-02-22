---
title: 'SessionRuntime Execute Action'
docname: 'session_runtime_execute_action'
order: 2
id: 'a6ce'
---
## Overview

Run simple lifecycle actions through `SessionRuntime::execute()`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Session\Actions\ResumeSession;
use Cognesy\Agents\Session\Actions\SuspendSession;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime-example'));

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'runtime-agent',
    description: 'Runtime action demo',
    systemPrompt: 'You are helpful.',
)));

$sessionId = SessionId::from($created->sessionId());
$suspended = $runtime->execute($sessionId, new SuspendSession());
$resumed = $runtime->execute($sessionId, new ResumeSession());

echo "=== Result ===\n";
echo 'Initial status: ' . $created->status()->value . "\n";
echo 'After suspend: ' . $suspended->status()->value . "\n";
echo 'After resume: ' . $resumed->status()->value . "\n";
echo 'Current version: ' . $resumed->version() . "\n";

assert($suspended->status()->value === 'suspended');
assert($resumed->status()->value === 'active');
assert($resumed->version() === 3);
?>
```
