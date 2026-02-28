---
title: 'Session Runtime Hooks'
docname: 'session_runtime_hooks'
order: 7
id: 'f2a1'
---
## Overview

Intercept `SessionRuntime` lifecycle stages with `SessionHookStack`
to apply cross-cutting session policies without replacing runtime flow.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionHookStack;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$events = new EventDispatcher('session-runtime-hooks-example');

$capabilities = new AgentCapabilityRegistry();
$loopFactory = new DefinitionLoopFactory($capabilities);

$trace = new class {
    /** @var list<string> */
    public array $stages = [];
};

$hook = new class($trace) implements CanControlAgentSession {
    public function __construct(private object $trace) {}

    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        $this->trace->stages[] = $stage->value;
        return match ($stage) {
            AgentSessionStage::AfterLoad => $session->withState(
                $session->state()->withMetadata('hook.after_load', true)
            ),
            AgentSessionStage::AfterAction => $session->withState(
                $session->state()->withMetadata('hook.after_action', true)
            ),
            AgentSessionStage::BeforeSave => $session->suspended(),
            default => $session,
        };
    }
};

$hooks = SessionHookStack::empty()->with($hook, priority: 100);
$runtime = new SessionRuntime($repo, $events, $hooks);

$created = $repo->create($factory->create(new AgentDefinition(
    name: 'hooks-agent',
    description: 'Session hooks demo',
    systemPrompt: 'You are helpful. Reply in one short sentence.',
    llmConfig: 'openai',
)));

$sessionId = SessionId::from($created->sessionId());
$updated = $runtime->execute(
    $sessionId,
    new SendMessage('Do one short task while hooks are active.', $loopFactory),
);
$loaded = $runtime->getSession($sessionId);

echo "=== Result ===\n";
echo 'Status after execute: ' . $updated->status()->value . "\n";
echo 'Persisted status: ' . $loaded->status()->value . "\n";
echo 'Hook stage trace: ' . implode(', ', $trace->stages) . "\n";
echo 'Metadata hook.after_load: ' . (($loaded->state()->metadata()->get('hook.after_load') ?? false) ? 'true' : 'false') . "\n";
echo 'Last response: ' . ($loaded->state()->finalResponse()->toString() ?: 'No response') . "\n";
?>
```
