# Symfony Runtime Surfaces

`packages/symfony` exposes three distinct runtime surfaces.

Use this rule of thumb:

- use the core primitives when you want direct inference, embeddings, or structured output
- use `AgentCtrl` when you want Symfony-managed execution of external CLI code agents such as Codex or Claude Code
- use native agents when you want the in-process `Cognesy\Agents` runtime with definitions, tools, capabilities, and resumable sessions

## 1. Core Primitives

This is the lowest-level surface.

Use it when your Symfony app needs:

- direct chat or completion calls
- embeddings generation
- structured extraction without agent orchestration

Primary services:

- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\StructuredOutput`

Example:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\Inference;

final readonly class AiRuntime
{
    public function __construct(
        private Inference $inference,
        private Embeddings $embeddings,
        private StructuredOutput $structuredOutput,
    ) {}
}
// @doctest id="dfa6"
```

Choose this surface when you do not need agent-style continuation, registry-driven tools, or external code-agent processes.

## 2. AgentCtrl

`AgentCtrl` is the Symfony surface for external code agents.

Use it when you want to run tools such as:

- Codex
- Claude Code
- OpenCode
- Gemini CLI style backends

Primary services:

- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessage`

Example:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RefactorController
{
    public function __construct(
        private SymfonyAgentCtrlRuntimes $agentCtrl,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): JsonResponse
    {
        $runtime = $this->agentCtrl->http();
        $prompt = 'Refactor src/Service/UserService.php';

        if ($runtime->policy()->requiresMessengerDispatch()) {
            $this->messageBus->dispatch(new ExecuteAgentCtrlPromptMessage(
                prompt: $prompt,
                backend: 'codex',
            ));

            return new JsonResponse(['queued' => true]);
        }

        $response = $runtime->codex()->execute($prompt);

        return new JsonResponse([
            'text' => $response->text(),
            'handoff' => $runtime->handoff($response)?->toArray(),
        ]);
    }
}
// @doctest id="fd11"
```

Choose `AgentCtrl` when the real unit of work is an external coding agent process, not an in-process InstructorPHP runtime.

## 3. Native Agents

Native agents are the in-process `Cognesy\Agents` runtime.

Use them when you want:

- package-owned definitions, tools, capabilities, and schemas in Symfony's container
- resumable session state
- direct control over the runtime without shelling out to external agent CLIs

Primary services:

- `Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions`
- `Cognesy\Agents\Tool\Contracts\CanManageTools`
- `Cognesy\Agents\Capability\CanManageAgentCapabilities`
- `Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas`
- `Cognesy\Agents\Session\Contracts\CanManageAgentSessions`
- `Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage`

Direct runtime example:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;

final readonly class SupportAgentRuntime
{
    public function __construct(
        private CanManageAgentDefinitions $definitions,
        private CanManageAgentSessions $sessions,
        private CanInstantiateAgentLoop $loops,
    ) {}

    public function start(string $prompt): string
    {
        $session = $this->sessions->create($this->definitions->get('support-agent'));
        $session = $this->sessions->execute($session->sessionId(), new SendMessage(
            message: $prompt,
            loopFactory: $this->loops,
        ));

        return $session->sessionId()->value;
    }
}
// @doctest id="3664"
```

Queued resume example:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ResumeSupportAgentController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(string $sessionId): void
    {
        $this->bus->dispatch(new ExecuteNativeAgentPromptMessage(
            definition: 'support-agent',
            prompt: 'Continue the previous task',
            sessionId: $sessionId,
        ));
    }
}
// @doctest id="8657"
```

Choose native agents when you want long-lived application-owned agent definitions and resumable state inside the Symfony service graph.

## Which One Should You Choose?

Choose core primitives when:

- you want lower-level LLM building blocks
- orchestration belongs in your own application code

Choose `AgentCtrl` when:

- the agent is an external CLI process
- you want runtime context policy around HTTP, CLI, and Messenger dispatch

Choose native agents when:

- the agent runtime should stay in PHP
- you want Symfony-managed definitions, tools, and session persistence

The key distinction is:

- `AgentCtrl` orchestrates external agent processes
- native agents orchestrate the in-process `Cognesy\Agents` runtime
- core primitives are not agent orchestration at all
