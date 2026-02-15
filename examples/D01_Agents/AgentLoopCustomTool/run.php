---
title: 'Agent with Custom Tool'
docname: 'agent_loop_custom_tool'
order: 4
id: '1f01'
---
## Overview

Build a custom tool by extending `BaseTool`. Override `__invoke(mixed ...$args)` to
implement the tool logic, use `$this->arg()` to extract named parameters, and override
`toToolSchema()` to define the parameter schema for the LLM.

This example creates a `SystemInfoTool` that reports memory usage, PHP version, and
other runtime information. The agent calls it when asked about the system.

Key concepts:
- `BaseTool`: Abstract base class for custom tools
- `__invoke(mixed ...$args)`: The method the agent calls
- `$this->arg()`: Extract named or positional parameters from args
- `toToolSchema()`: Define the JSON Schema the LLM sees for this tool
- `$this->agentState`: Access current agent state from within a tool

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Messages\Messages;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

// Custom tool that reports system information
class SystemInfoTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'system_info',
            description: 'Returns current system resource usage and PHP runtime information.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $category = (string) $this->arg($args, 'category', 0, 'all');
        $info = [];

        if ($category === 'memory' || $category === 'all') {
            $memUsage = memory_get_usage(true);
            $memPeak = memory_get_peak_usage(true);
            $info[] = sprintf("Memory: %.2f MB (peak: %.2f MB)", $memUsage / 1048576, $memPeak / 1048576);
        }

        if ($category === 'php' || $category === 'all') {
            $info[] = "PHP Version: " . PHP_VERSION;
            $info[] = "OS: " . PHP_OS;
            $info[] = "SAPI: " . PHP_SAPI;
        }

        if ($category === 'all') {
            $info[] = "PID: " . getmypid();
            $info[] = "Uptime: " . (int)(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . "s";

            // Show agent context if available
            if ($this->agentState !== null) {
                $info[] = "Agent step: " . $this->agentState->stepCount();
                $info[] = "Agent tokens: " . $this->agentState->usage()->total();
            }
        }

        return implode("\n", $info);
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('category', 'What to check: "memory", "php", or "all"'),
                ])
                ->withRequiredProperties([])
        )->toArray();
    }
}

// AgentConsoleLogger shows execution lifecycle events on the console
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Create loop with the custom tool
$loop = AgentLoop::default()
    ->withTool(new SystemInfoTool())
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withMessages(
    Messages::fromString('Check the current memory usage and PHP version. Report back concisely.')
);

echo "=== Agent Execution ===\n\n";
$finalState = $loop->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
?>
```
