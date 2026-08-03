---
title: 'Coding Agent Creates and Repairs an Example'
docname: 'coding_agent_creates_example'
order: 6
id: 'c7f2'
tags:
  - 'agent-templates'
  - 'coding-agent'
  - 'tools'
---
## Overview

Run a template-defined coding agent whose `UseSystemPrompt` capability derives
guidance for the provider-familiar `read`, `bash`, `edit`, and `write` tools.
The task lives in `goal.md`, so it can be revised independently from the PHP
wiring.

The agent reads `examples/A01_Basics/Basic/run.php`, creates a different
InstructorPHP example under a timestamped `/tmp/instructor-php/agent-test/`
workspace, runs it, repairs failures, and verifies the result. Repository inputs
remain read-only; generated artifacts stay available in `/tmp` for inspection.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Capability\Coding\UseCodingTools;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;

// --- Reusable coding-agent workflow -----------------------------------------

final readonly class CodingAgentRunResult
{
    public function __construct(
        public AgentState $state,
        public string $workspace,
        public string $generatedExample,
        public string $verificationCommand,
        public string $verificationOutput,
    ) {}
}

final readonly class CodingAgentExample
{
    private function __construct(
        private string $projectRoot,
        private string $workspace,
        private ?CanHandleEvents $events,
    ) {}

    public static function inTemporaryWorkspace(
        string $projectRoot,
        ?CanHandleEvents $events = null,
    ): self {
        $workspace = '/tmp/instructor-php/agent-test/'
            . date('Ymd-His') . '-' . bin2hex(random_bytes(3));

        if (!mkdir($workspace, 0755, true) && !is_dir($workspace)) {
            throw new RuntimeException("Cannot create workspace: {$workspace}");
        }

        return new self($projectRoot, $workspace, $events);
    }

    public function __invoke(): CodingAgentRunResult {
        // 1. Resolve the declarative definition into a runnable loop.
        $definition = $this->definition();
        $loop = (new DefinitionLoopFactory(
            capabilities: $this->capabilities(),
            events: $this->events,
        ))->instantiateAgentLoop($definition);

        // 2. Seed the initial conversation and let the loop own tool execution.
        $state = (new DefinitionStateFactory())->instantiateAgentState(
            $definition,
            AgentState::empty()->withUserMessage($this->task()),
        );
        $final = $loop->execute($state);

        // 3. Require a completed run and artifact before trying to execute it.
        $generatedExample = $this->workspace . '/run.php';
        if ($final->status()->value !== 'completed') {
            throw new RuntimeException(
                "Agent stopped with status: {$final->status()->value}",
            );
        }

        if (!is_file($generatedExample)) {
            throw new RuntimeException("Agent did not create: {$generatedExample}");
        }

        // 4. Verify the artifact independently of the agent's own report.
        $verificationCommand = 'php ' . escapeshellarg($generatedExample);
        $verificationOutput = BashTool::inDirectory($this->projectRoot)(
            $verificationCommand,
        );

        if (str_contains($verificationOutput, 'Exit code:')) {
            throw new RuntimeException("Generated example failed:\n{$verificationOutput}");
        }

        if (!str_contains($verificationOutput, 'Example status: verified')) {
            throw new RuntimeException(
                "Generated example is not verified:\n{$verificationOutput}",
            );
        }

        return new CodingAgentRunResult(
            state: $final,
            workspace: $this->workspace,
            generatedExample: $generatedExample,
            verificationCommand: $verificationCommand,
            verificationOutput: $verificationOutput,
        );
    }

    public function workspace(): string {
        return $this->workspace;
    }

    private function task(): string {
        $goalTemplate = file_get_contents(__DIR__ . '/goal.md');
        if ($goalTemplate === false) {
            throw new RuntimeException('Cannot read the coding-agent goal.');
        }

        return strtr($goalTemplate, [
            '{{SOURCE_EXAMPLE}}' => $this->projectRoot . '/examples/A01_Basics/Basic/run.php',
            '{{PROJECT_ROOT}}' => $this->projectRoot,
            '{{WORKSPACE}}' => $this->workspace,
        ]);
    }

    private function capabilities(): AgentCapabilityRegistry {
        $capabilities = new AgentCapabilityRegistry();
        $capabilities->register('coding.tools', new UseCodingTools($this->projectRoot));
        $capabilities->register('coding.prompt', new UseSystemPrompt());
        $capabilities->register(
            'coding.guards',
            new UseGuards(maxSteps: 20, maxTokens: 32768, maxExecutionTime: 240),
        );

        return $capabilities;
    }

    private function definition(): AgentDefinition {
        return new AgentDefinition(
            name: 'coding-example-agent',
            description: 'Creates and repairs an isolated InstructorPHP example.',
            systemPrompt: 'You are an expert coding assistant.',
            llmConfig: 'openai',
            capabilities: new NameList('coding.tools', 'coding.prompt', 'coding.guards'),
        );
    }
}

// --- Shared event channel ----------------------------------------------------

$events = new EventDispatcher('coding-example-agent');

// --- Raw agent setup ---------------------------------------------------------

$example = CodingAgentExample::inTemporaryWorkspace(
    projectRoot: dirname(__DIR__, 3),
    events: $events,
);

// --- Example instrumentation ------------------------------------------------

$observer = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);
$events->wiretap($observer->wiretap());

// --- Execute and inspect -----------------------------------------------------

echo "=== Coding Agent Execution ===\n";
echo "Workspace: {$example->workspace()}\n\n";

$result = $example();

echo "\n=== Result ===\n";
echo "Generated example: {$result->generatedExample}\n";
echo "Verification: {$result->verificationCommand}\n";
echo 'Agent response: '
    . ($result->state->finalResponse()->toString() ?: 'No response')
    . "\n";
echo "\n{$result->verificationOutput}\n";

assert($result->state->status()->value === 'completed');
assert(is_file($result->generatedExample));
assert(str_contains($result->verificationOutput, 'Example status: verified'));
?>
```
