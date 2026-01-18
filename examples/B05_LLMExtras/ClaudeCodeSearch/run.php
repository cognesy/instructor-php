<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ResultEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\TextContent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ToolUseContent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Common\Execution\ExecutionPolicy;

$claudePath = trim((string) shell_exec('command -v claude'));
$stdbufPath = trim((string) shell_exec('command -v stdbuf'));
if ($claudePath === '' || $stdbufPath === '') {
    print("Claude CLI or stdbuf not found. Install Claude Code CLI and coreutils before running this example.\n");
    exit(0);
}

// Ensure we're running from monorepo root
$projectRoot = dirname(__DIR__, 3);
if (!file_exists($projectRoot . '/examples')) {
    die("Error: Must run from project root. Current dir: " . getcwd() . "\n");
}
chdir($projectRoot);
print("Working directory: " . getcwd() . "\n\n");

// Step 1: Define an agentic search task
$searchPrompt = <<<'PROMPT'
Complete this task in steps:
1. Use Glob or find command to locate PHP files with "validation" in the filename under ./examples
2. Read the contents of relevant PHP file
3. Analyze the code and provide a concise explanation (under 200 words) covering:
   - What validation is being performed
   - What validation constraints/attributes are used
   - How validation is triggered
   - What happens when validation fails

Provide your final explanation as a clear, structured response.
PROMPT;

$request = new ClaudeRequest(
    prompt: $searchPrompt,
    outputFormat: OutputFormat::StreamJson,
    permissionMode: PermissionMode::BypassPermissions,  // Allow agent to freely read files
    includePartialMessages: true,
    maxTurns: 10,  // Allow multiple turns for exploration and explanation
    verbose: true,
);

print("Initiating agentic search...\n");
print("Task: Find and explain response validation example\n");
print(str_repeat('=', 80) . "\n\n");

// Step 2: Build command with Host driver for direct filesystem access
$builder = new ClaudeCommandBuilder();
$spec = $builder->buildHeadless($request);

// Step 3: Use Host driver with policy that allows filesystem access
$policy = ExecutionPolicy::custom(
    timeoutSeconds: 120,
    networkEnabled: false,  // No network needed for local file search
    stdoutLimitBytes: 10 * 1024 * 1024,
    stderrLimitBytes: 2 * 1024 * 1024,
    baseDir: $projectRoot,
    inheritEnv: true,
);

$executor = new SandboxCommandExecutor(
    policy: $policy,
    driver: SandboxDriver::Host,  // Direct filesystem access
);

print("Executing Claude Code CLI agent...\n");
print(str_repeat('-', 80) . "\n");

// Step 4: Execute with real-time streaming callback
$fullResponse = '';
$toolCalls = [];
$errors = [];
$lineBuffer = '';

$streamingCallback = function(string $type, string $chunk) use (&$fullResponse, &$toolCalls, &$errors, &$lineBuffer): void {
    // Buffer incoming chunks and process complete JSON lines
    $lineBuffer .= $chunk;
    $lines = explode("\n", $lineBuffer);

    // Keep the last incomplete line in the buffer
    $lineBuffer = array_pop($lines);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        // Parse JSON line into typed DTO
        $data = json_decode($trimmed, true);
        if (!is_array($data)) {
            continue;
        }

        $event = StreamEvent::fromArray($data);

        // Handle different event types with proper type safety
        if ($event instanceof MessageEvent) {
            foreach ($event->message->textContent() as $textContent) {
                print("\n📝 Agent: {$textContent->text}\n");
                flush();
                $fullResponse .= $textContent->text;
            }

            foreach ($event->message->toolUses() as $toolUse) {
                print("\n🔧 Tool Call: {$toolUse->name}\n");
                print("   ID: {$toolUse->id}\n");
                flush();

                $toolCalls[] = $toolUse;
            }
        }

        if ($event instanceof ResultEvent) {
            print("\n🎯 Result: {$event->result}\n");
            flush();
        }

        if ($event instanceof ErrorEvent) {
            print("\n❌ Error: {$event->error}\n");
            flush();
            $errors[] = $event->error;
        }
    }
};

// Execute with streaming
$execResult = $executor->executeStreaming($spec, $streamingCallback);

// Process any remaining buffered line
if (!empty($lineBuffer)) {
    $trimmed = trim($lineBuffer);
    if ($trimmed !== '') {
        $data = json_decode($trimmed, true);
        if (is_array($data)) {
            $event = StreamEvent::fromArray($data);
            // Process final event if needed
        }
    }
}

$eventCount = count($toolCalls) + count($errors);
$toolCount = count($toolCalls);

print("\n" . str_repeat('=', 80) . "\n");

// Summary
print("\n📊 Execution Summary:\n");
print("   Tool calls: {$toolCount}\n");
print("   Errors: " . count($errors) . "\n");
print("   Exit code: {$execResult->exitCode()}\n");

if (!empty($toolCalls)) {
    print("\n🔧 Tools used:\n");
    $toolsByName = [];
    foreach ($toolCalls as $tool) {
        $toolsByName[$tool->name] = ($toolsByName[$tool->name] ?? 0) + 1;
    }
    foreach ($toolsByName as $toolName => $count) {
        print("   - {$toolName}: {$count} time(s)\n");
    }
}

if ($execResult->exitCode() === 0) {
    print("\n✓ Agent search completed successfully\n");
} else {
    print("\n✗ Agent search failed with exit code: {$execResult->exitCode()}\n");
    if ($execResult->stderr()) {
        print("STDERR: " . substr($execResult->stderr(), 0, 500) . "\n");
    }
    exit(1);
}
?>
