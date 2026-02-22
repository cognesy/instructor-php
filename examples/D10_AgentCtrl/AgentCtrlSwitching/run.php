---
title: 'Agent Control Runtime Switching'
docname: 'agent_ctrl_switching'
id: '87de'
---
## Overview

AgentCtrl provides a unified API that works across multiple CLI-based code agents. This
enables runtime switching between different backends (Claude Code, OpenCode, Codex, Gemini)
without changing your application code. Useful for comparing agent performance, failover
scenarios, or A/B testing.

Key concepts:
- `AgentType` enum: Specify which agent backend to use
- Unified API: Same methods work across all agent types
- Runtime selection: Choose agent dynamically based on configuration or logic
- `AgentCtrlConsoleLogger`: Shared logger works across all agent types

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\Enum\AgentType;

// Shared console logger - works across all agent types
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
);

$prompt = 'What design pattern does a class with a static make() method implement? Answer in one sentence.';

// Test the same prompt with multiple agents
$agents = [
    'opencode' => 'OpenCode',
    'claude-code' => 'Claude Code',
    'codex' => 'Codex',
];

foreach ($agents as $agentId => $agentName) {
    echo "=== Testing: {$agentName} ===\n\n";

    $startTime = microtime(true);

    try {
        $builder = AgentCtrl::make(AgentType::from($agentId))
            ->wiretap($logger->wiretap());

        // Apply agent-specific configuration
        if ($agentId === 'claude-code') {
            $builder->withMaxTurns(1);
        }

        $response = $builder->execute($prompt);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        echo "\n=== Result ({$elapsed}ms) ===\n";
        if ($response->isSuccess()) {
            echo "Answer: " . $response->text() . "\n";

            if ($response->usage) {
                echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
            }
            if ($response->cost) {
                echo "Cost: $" . number_format($response->cost, 4) . "\n";
            }
        } else {
            echo "Failed (exit code: {$response->exitCode})\n";
        }
    } catch (Throwable $e) {
        echo "Error: {$e->getMessage()}\n";
    }

    echo "\n";
}
?>
```

## Expected Output

```
=== Testing: OpenCode ===

14:32:15.123 [opencode] [EXEC] Execution started [prompt=What design pattern does a class...]
14:32:16.357 [opencode] [DONE] Execution completed [exit=0, tools=0, tokens=62]

=== Result (1234ms) ===
Answer: The Factory Method pattern, which uses a static method to create
and return instances of different subclasses based on parameters.
Tokens: 38 in / 24 out
Cost: $0.0008

=== Testing: Claude Code ===

14:32:17.123 [claude-code] [EXEC] Execution started [prompt=What design pattern does a class...]
14:32:19.279 [claude-code] [DONE] Execution completed [exit=0, tools=0, cost=$0.0015, tokens=64]

=== Result (2156ms) ===
Answer: This is the Factory Method pattern, where a static factory method
determines which concrete class to instantiate based on input.
Tokens: 38 in / 26 out
Cost: $0.0015

=== Testing: Codex ===

14:32:20.123 [codex] [EXEC] Execution started [prompt=What design pattern does a class...]
14:32:21.110 [codex] [DONE] Execution completed [exit=0, tools=0, tokens=60]

=== Result (987ms) ===
Answer: A class using static make() for conditional instantiation typically
implements the Factory Method or Static Factory pattern.
Tokens: 38 in / 22 out
Cost: $0.0012
```

## Key Points

- **Unified API**: Same interface across all agent backends
- **Shared logger**: One `AgentCtrlConsoleLogger` works across all agent types, prefixing output with `[opencode]`, `[claude-code]`, etc.
- **Runtime selection**: Choose agent dynamically based on requirements
- **Agent comparison**: Run the same prompt across multiple agents to compare
- **Failover capability**: Try alternative agents if primary fails
- **Agent-specific tuning**: Apply configuration based on agent characteristics
- **Performance comparison**: Measure response time and cost across agents
- **Use cases**: A/B testing, load balancing, fallback strategies, feature parity testing
