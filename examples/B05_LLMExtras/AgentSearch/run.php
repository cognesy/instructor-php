<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Enums\AgentType;
use Cognesy\Addons\Agent\Subagents\DefaultAgentCapability;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Addons\Agent\Tools\ReadFileTool;
use Cognesy\Messages\Messages;

/**
 * Agent Search Example
 *
 * Demonstrates agentic search using subagents for:
 * - Searching for files matching a pattern
 * - Reading file contents
 * - Generating a synthesized answer
 *
 * The main agent orchestrates subagents that each handle specific tasks.
 *
 * Usage:
 *   php run.php [preset]
 *
 * Examples:
 *   php run.php              # Uses default LLM connection
 *   php run.php openai       # Uses OpenAI preset
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║            Agent - Agentic Search with Subagents               ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

// Get optional LLM preset from command line
$llmPreset = $argv[1] ?? null;

if ($llmPreset) {
    print("Using LLM preset: {$llmPreset}\n\n");
}

// Get project root directory
$projectRoot = dirname(__DIR__, 3);

/**
 * Custom tool: Search for files matching a glob pattern
 */
class SearchFilesTool extends BaseTool
{
    private string $baseDir;

    public function __construct(string $baseDir) {
        parent::__construct(
            name: 'search_files',
            description: 'Search for files matching a glob pattern in the project',
        );
        $this->baseDir = $baseDir;
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $pattern = $args['pattern'] ?? $args[0] ?? '*.php';

        // Use glob to find files
        $fullPattern = $this->baseDir . '/' . $pattern;
        $files = glob($fullPattern, GLOB_BRACE) ?: [];

        // Limit results
        $files = array_slice($files, 0, 10);

        if (empty($files)) {
            return "No files found matching pattern: {$pattern}";
        }

        // Return relative paths
        $relativePaths = array_map(
            fn($f) => str_replace($this->baseDir . '/', '', $f),
            $files
        );

        return "Found " . count($relativePaths) . " files:\n" . implode("\n", $relativePaths);
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'Glob pattern to match files (e.g., "src/*.php", "**/*.md")',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
            ],
        ];
    }
}

/**
 * Custom tool: Spawn a research subagent
 */
class ResearchSubagentTool extends BaseTool
{
    private Agent $parentAgent;
    private string $baseDir;

    public function __construct(Agent $parentAgent, string $baseDir) {
        parent::__construct(
            name: 'research_subagent',
            description: 'Spawn a subagent to research files and return a summary. Use for reading and analyzing file contents.',
        );
        $this->parentAgent = $parentAgent;
        $this->baseDir = $baseDir;
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $task = $args['task'] ?? $args[0] ?? '';
        $files = $args['files'] ?? $args[1] ?? [];

        if (empty($task)) {
            return "Error: task is required";
        }

        // Create subagent with read-only file access
        $subagentTools = new Tools(
            ReadFileTool::inDirectory($this->baseDir),
        );

        $subagent = AgentFactory::default(tools: $subagentTools);

        // Build context with file list
        $fileList = is_array($files) ? implode(', ', $files) : $files;
        $prompt = "You are a research assistant. {$task}\n";
        if (!empty($fileList)) {
            $prompt .= "Relevant files to examine: {$fileList}\n";
        }
        $prompt .= "Provide a concise summary of your findings.";

        $subState = AgentState::empty()->withMessages(
            Messages::fromString($prompt)
        );

        // Run subagent
        $finalState = $subagent->finalStep($subState);

        return $finalState->currentStep()?->outputMessages()->toString() ?? 'No findings';
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task' => [
                            'type' => 'string',
                            'description' => 'The research task to perform',
                        ],
                        'files' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of file paths to examine',
                        ],
                    ],
                    'required' => ['task'],
                ],
            ],
        ];
    }
}

// Create tools for the main orchestrator agent
$searchTool = new SearchFilesTool($projectRoot);

// Create continuation criteria with higher step limit for agentic search
$continuationCriteria = new \Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria(
    new \Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit(10, fn($s) => $s->stepCount()),
    new \Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit(32768, fn($s) => $s->usage()->total()),
    new \Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck(fn($s) => $s->stepCount() === 0 || ($s->currentStep()?->hasToolCalls() ?? false)),
);

// Create main agent first (without subagent tool)
$mainAgent = AgentFactory::default(
    tools: new Tools($searchTool),
    llmPreset: $llmPreset,
    continuationCriteria: $continuationCriteria,
);

// Now add the research subagent tool with reference to main agent
$researchTool = new ResearchSubagentTool($mainAgent, $projectRoot);
$mainAgent = $mainAgent->withTools(new Tools($searchTool, $researchTool));

// Initialize state with a research question
$question = "What testing framework does this PHP project use? " .
    "Search for test configuration files and examine them to determine the testing setup.";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

print("Question: {$question}\n\n");
print("Processing with subagents...\n\n");

// Run agent step by step to show orchestration
$stepNum = 0;
while ($mainAgent->hasNextStep($state)) {
    $state = $mainAgent->nextStep($state);
    $step = $state->currentStep();
    $stepNum++;

    $stepType = $step->stepType()->value;

    print("Step {$stepNum}: [{$stepType}]\n");

    // Show errors if any
    if ($step->hasErrors()) {
        foreach ($step->errors() as $error) {
            print("  ⚠ Error: " . $error->getMessage() . "\n");
        }
    }

    // Show tool calls
    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            $args = $toolCall->args();
            $argStr = isset($args['pattern']) ? "pattern={$args['pattern']}" :
                     (isset($args['task']) ? "task=\"" . substr($args['task'], 0, 40) . "...\"" : '');
            print("  → {$toolCall->name()}({$argStr})\n");
        }
    }

    // Show content preview if final response
    if (!$step->hasToolCalls() && $step->outputMessages()->count() > 0) {
        print("  → Final response ready\n");
    }
}

print("\n");

// Extract and display the response
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

print("Answer:\n");
print(str_repeat("─", 68) . "\n");
print($response . "\n");
print(str_repeat("─", 68) . "\n\n");

// Display stats
print("Stats:\n");
print("  Steps: {$state->stepCount()}\n");
print("  Status: {$state->status()->value}\n");
$usage = $state->usage();
print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");

// Show hint if failed
if ($state->status() === AgentStatus::Failed && $usage->total() === 0) {
    print("\nHint: Status 'failed' with 0 tokens usually means the LLM connection failed.\n");
    print("      Try: php run.php openai    # If you have OPENAI_API_KEY set\n");
}
