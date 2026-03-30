# Symfony Quickstart

`packages/symfony` now gives Symfony applications a real first-party starting point:

- one public `instructor` config root
- core runtime bindings for Inference, Embeddings, StructuredOutput, HTTP, and events
- AgentCtrl builder and runtime adapters for `cli`, `http`, and `messenger`

## 1. Install The Package

```bash
composer require cognesy/instructor-symfony
```

## 2. Register The Bundle

Add the bundle to `config/bundles.php` if your application does not register it automatically:

```php
<?php

return [
    // ...
    Cognesy\Instructor\Symfony\InstructorSymfonyBundle::class => ['all' => true],
];
```

## 3. Add Minimal Core Config

Create `config/packages/instructor.yaml`:

```yaml
instructor:
  connections:
    default: openai
    items:
      openai:
        driver: openai
        api_key: '%env(OPENAI_API_KEY)%'
        model: gpt-4o-mini

  embeddings:
    default: openai
    connections:
      openai:
        driver: openai
        api_key: '%env(OPENAI_API_KEY)%'
        model: text-embedding-3-small

  extraction:
    output_mode: json_schema
    max_retries: 1

  http:
    driver: symfony

  events:
    dispatch_to_symfony: true
```

This baseline is enough to resolve the current core services from the container:

- `Cognesy\Config\Contracts\CanProvideConfig`
- `Cognesy\Http\Contracts\CanSendHttpRequests`
- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\StructuredOutput`

## 4. Inject The Core Runtime Services

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
```

## 5. Optionally Enable AgentCtrl

If you also want Symfony-managed code-agent execution, extend the same config file:

```yaml
instructor:
  agent_ctrl:
    enabled: true
    default_backend: codex

    defaults:
      timeout: 300
      working_directory: '%kernel.project_dir%'
      sandbox_driver: host

    execution:
      transport: messenger
      allow_cli: true
      allow_http: true
      allow_messenger: true

    continuation:
      mode: continue_last
      session_key: agent_ctrl_session_id
      persist_session_id: true
      allow_cross_context_resume: true

    backends:
      codex:
        model: codex
```

The AgentCtrl container entry points are:

- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessage`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage`

Example controller using the HTTP runtime:

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
```

## 6. Optionally Enable Persisted Native-Agent Sessions

If you want resumable native-agent sessions across CLI, HTTP, or Messenger worker boots, enable the file-backed adapter:

```yaml
instructor:
  sessions:
    store: file
    file:
      directory: '%kernel.cache_dir%/instructor/agent-sessions'
```

This keeps the persistence seam package-owned while still letting applications override `Cognesy\Agents\Session\Contracts\CanStoreSessions` if they need a custom backend later.

## 7. Current Boundaries

Already supported:

- bundle registration under `Cognesy\Instructor\Symfony\`
- core runtime config translation and service wiring
- Symfony-aware HTTP transport selection
- package-owned event bus with optional Symfony event bridging
- AgentCtrl builder and runtime adapters
- native-agent session store selection with built-in memory and file adapters
- package-owned testing patterns built around public container override seams
- telemetry exporter selection, projector composition, and lifecycle hooks

Still landing in later tasks:

- logging presets
- split-package publication bootstrap and Packagist registration

For a practical “which runtime surface should I use?” guide, see `packages/symfony/docs/runtime-surfaces.md`.
For the detailed config surface, see `packages/symfony/docs/configuration.md`.
For the telemetry-specific runtime and exporter guidance, see `packages/symfony/docs/telemetry.md`.
For the package testing model and public helper boundary, see `packages/symfony/docs/testing.md`.
For app-shape observability and delivery guidance, see `packages/symfony/docs/operations.md`.
For migration from older scattered Symfony glue, see `packages/symfony/docs/migration.md`.
