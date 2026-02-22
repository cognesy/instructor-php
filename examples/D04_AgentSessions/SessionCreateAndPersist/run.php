---
title: 'Session Create and Persist'
docname: 'session_create_and_persist'
order: 1
id: 'f93a'
---
## Overview

Create a session from `AgentDefinition`, persist it, then load and save updated state.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());

$definition = new AgentDefinition(
    name: 'session-agent',
    description: 'Session persistence demo',
    systemPrompt: 'You are persistent.',
);

$session = $factory->create($definition, AgentState::empty()->withUserMessage('hello'));
$created = $repo->create($session);
$loaded = $repo->load(\Cognesy\Agents\Session\SessionId::from($created->sessionId()));

$updated = $repo->save($created->withState($created->state()->withMetadata('phase', 'saved')));

echo "=== Result ===\n";
echo "Session ID: {$created->sessionId()}\n";
echo "Version after create: {$created->version()}\n";
echo "Version after save: {$updated->version()}\n";
echo 'Metadata phase: ' . ($updated->state()->metadata()->get('phase') ?? 'missing') . "\n";

assert($loaded !== null);
assert($created->version() === 1);
assert($updated->version() === 2);
assert($updated->state()->metadata()->get('phase') === 'saved');
?>
```
