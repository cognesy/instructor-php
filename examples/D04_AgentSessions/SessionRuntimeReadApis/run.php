---
title: 'SessionRuntime Read APIs'
docname: 'session_runtime_read_apis'
order: 3
id: 'b8d2'
---
## Overview

Use runtime read APIs after real session work:
`getSession`, `getSessionInfo`, and `listSessions`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Session\Actions\SendMessage;
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

$one = $repo->create($factory->create(new AgentDefinition(
    name: 'agent-one',
    description: 'first',
    systemPrompt: 'You are one.',
    llmConfig: 'openai',
)));
$two = $repo->create($factory->create(new AgentDefinition(
    name: 'agent-two',
    description: 'second',
    systemPrompt: 'You are two.',
    llmConfig: 'openai',
)));

$runtime->execute($one->sessionId(), new SendMessage('Say one sentence about session one.', $loopFactory));
$runtime->execute($two->sessionId(), new SendMessage('Say one sentence about session two.', $loopFactory));

$sessionId = $one->sessionId();
$session = $runtime->getSession($sessionId);
$info = $runtime->getSessionInfo($sessionId);
$list = $runtime->listSessions();

echo "=== Result ===\n";
echo 'Loaded session: ' . $session->sessionId() . "\n";
echo 'Session info status: ' . $info->status()->value . "\n";
echo 'Session message count: ' . $session->state()->messages()->count() . "\n";
echo 'Session last response: ' . ($session->state()->finalResponse()->toString() ?: 'No response') . "\n";
echo 'List count: ' . $list->count() . "\n";
?>
```
