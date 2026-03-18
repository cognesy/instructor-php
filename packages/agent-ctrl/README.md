# AgentCtrl

Unified CLI bridge for code agents (Claude Code, OpenAI Codex, OpenCode) with one API and a normalized response type.

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Config\AgentCtrlConfig;

$response = AgentCtrl::codex()
    ->withConfig(new AgentCtrlConfig(
        timeout: 300,
        workingDirectory: getcwd() ?: null,
    ))
    ->execute('Summarize this repository.');

echo $response->text();
```

## Execution Identity

Each `execute()` or `executeStreaming()` call gets its own internal `executionId()`.
That id is the canonical correlation key for `agent-ctrl` events and telemetry.

`sessionId()` is different:

- `executionId()` is per run
- `sessionId()` is provider continuity metadata used for `continueSession()` and `resumeSession()`

That means multiple runs may share one `sessionId()` while still having different `executionId()` values.

```php
$response = AgentCtrl::codex()->execute('Create a plan.');

echo (string) $response->executionId(); // one run
echo (string) ($response->sessionId() ?? ''); // provider session, if available
```
