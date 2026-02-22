---
title: 'Session Conflict Handling'
docname: 'session_conflict_handling'
order: 6
id: 'e5f1'
---
## Overview

Conflicts are explicit exceptions. This example simulates stale write conflict.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'conflict-agent',
    description: 'Conflict demo',
    systemPrompt: 'You are helpful.',
)));

$sessionId = SessionId::from($created->sessionId());
$copyA = $repo->load($sessionId);
$copyB = $repo->load($sessionId);

assert($copyA !== null && $copyB !== null);

$repo->save($copyA->withState($copyA->state()->withMetadata('writer', 'A')));

try {
    $repo->save($copyB->withState($copyB->state()->withMetadata('writer', 'B')));
    throw new RuntimeException('Expected conflict was not thrown');
} catch (SessionConflictException $e) {
    echo "=== Result ===\n";
    echo 'Conflict detected as expected: ' . $e->getMessage() . "\n";
}
?>
```
