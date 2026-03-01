# AgentCtrl

Unified CLI bridge for code agents (Claude Code, OpenAI Codex, OpenCode) with one API and a normalized response type.

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Summarize this repository.');
echo $response->text();
```
