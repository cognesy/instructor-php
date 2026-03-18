---
title: 'Send AgentCtrl telemetry to Logfire'
docname: 'agent_ctrl_telemetry_logfire'
id: 'e61f'
tags:
  - 'agent-ctrl'
  - 'logfire'
  - 'telemetry'
---
## Overview

This example combines the built-in `AgentCtrlConsoleLogger` with Logfire export.
You still see the live execution locally, and the same run is also emitted as
correlated telemetry through the `AgentCtrl` projector.

Key concepts:
- `AgentCtrlConsoleLogger`: local execution visibility
- `AgentCtrlTelemetryProjector`: correlates the full run by `executionId`
- `executeStreaming()`: shows progress as the CLI agent works
- `bash` tool calls: make the telemetry trace reflect multi-step repository inspection
- `Telemetry::flush()`: sends the final telemetry batch

## Example

```php
<?php
require 'examples/boot.php';
require_once 'examples/_support/logfire.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

$hub = exampleLogfireHub('examples.d10.telemetry-logfire');
$bridge = new RuntimeEventBridge(new AgentCtrlTelemetryProjector($hub));

$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
    showStreaming: false,
);

$workDir = dirname(__DIR__, 3);

echo "=== Agent Execution Log ===\n\n";

$prompt = <<<'PROMPT'
Inspect this repository using the bash tool.

Requirements:
- Use bash at least 3 separate times.
- Do not combine commands with && or ;.
- Run these as separate bash calls:
  1. pwd
  2. ls examples/D10_AgentCtrl
  3. rg -n "Telemetry" examples/D10_AgentCtrl

Then explain in two short sentences what the AgentCtrl telemetry examples demonstrate.
PROMPT;

$response = AgentCtrl::codex()
    ->wiretap($logger->wiretap())
    ->wiretap($bridge->handle(...))
    ->withSandbox(SandboxMode::ReadOnly)
    ->inDirectory($workDir)
    ->executeStreaming($prompt);

$hub->flush();

$toolNames = array_map(
    static fn($toolCall): string => $toolCall->tool,
    $response->toolCalls,
);
$toolCallCount = count($toolNames);

echo "\n=== Result ===\n";
if (!$response->isSuccess()) {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
    exit(1);
}

echo "Answer: {$response->text()}\n";
echo "Execution ID: {$response->executionId()}\n";
echo "Tools used: " . implode(' > ', $toolNames) . "\n";
echo "Total tool calls: {$toolCallCount}\n";
if ($response->sessionId() !== null) {
    echo "Session ID: {$response->sessionId()}\n";
}
echo "Telemetry: flushed to Logfire\n";

assert($response->text() !== '');
assert($toolCallCount >= 3);
?>
```
