---
title: 'SessionRuntime Read APIs'
docname: 'session_runtime_read_apis'
order: 3
id: 'b8d2'
---
## Overview

Use runtime read APIs: `getSession`, `getSessionInfo`, and `listSessions`.

## Example

```php
<?php
require 'examples/boot.php';

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

$one = $repo->create($factory->create(new AgentDefinition('agent-one', 'first', 'You are one.')));
$two = $repo->create($factory->create(new AgentDefinition('agent-two', 'second', 'You are two.')));

$sessionId = SessionId::from($one->sessionId());
$session = $runtime->getSession($sessionId);
$info = $runtime->getSessionInfo($sessionId);
$list = $runtime->listSessions();

echo "=== Result ===\n";
echo 'Loaded session: ' . $session->sessionId() . "\n";
echo 'Session info status: ' . $info->status()->value . "\n";
echo 'List count: ' . $list->count() . "\n";

assert($session->sessionId() === $one->sessionId());
assert($info->sessionId() === $one->sessionId());
assert($list->count() === 2);
?>
```
