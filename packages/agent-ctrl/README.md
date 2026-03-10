# AgentCtrl

Unified CLI bridge for code agents (Claude Code, OpenAI Codex, OpenCode) with one API and a normalized response type.

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Config\AgentConfig;

$response = AgentCtrl::codex()
    ->withConfig(new AgentConfig(
        timeout: 300,
        workingDirectory: getcwd() ?: null,
    ))
    ->execute('Summarize this repository.');

echo $response->text();
```
