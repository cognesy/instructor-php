<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StepStartEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\TextEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StepFinishEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\Utils\Sandbox\Utils\ProcUtils;

if (ProcUtils::findOnPath('opencode', ProcUtils::defaultBinPaths()) === null) {
    print("OpenCode CLI not found. Install OpenCode CLI before running this example.\n");
    exit(1);
}

// Create a request - using JSON format for streaming events
$request = new OpenCodeRequest(
    prompt: 'Read the first 5 lines of composer.json in this directory and describe what you see.',
    outputFormat: OutputFormat::Json,
);

print("Streaming OpenCode CLI output...\n");
print("Prompt: {$request->prompt()}\n\n");

// Build command
$builder = new OpenCodeCommandBuilder();
$spec = $builder->buildRun($request);

// Execute with streaming callback
$executor = SandboxCommandExecutor::forOpenCode();

$execResult = $executor->executeStreaming($spec, function (string $type, string $chunk) {
    if ($type !== 'out') {
        return;
    }

    // Each line is a JSON object
    $lines = preg_split('/\r\n|\r|\n/', $chunk);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            continue;
        }

        $event = StreamEvent::fromArray($decoded);

        // Handle different event types
        match (true) {
            $event instanceof StepStartEvent => print("[STEP] Started (session: {$event->sessionId})\n"),
            $event instanceof TextEvent => handleText($event),
            $event instanceof ToolUseEvent => handleToolUse($event),
            $event instanceof StepFinishEvent => handleStepFinish($event),
            default => print("[{$event->type()}]\n"),
        };
    }
});

function handleText(TextEvent $event): void {
    $preview = substr($event->text, 0, 100);
    $suffix = strlen($event->text) > 100 ? '...' : '';
    print("[TEXT] {$preview}{$suffix}\n");
}

function handleToolUse(ToolUseEvent $event): void {
    print("[TOOL] {$event->tool} ({$event->status})\n");
    if ($event->isCompleted()) {
        $preview = substr($event->output, 0, 80);
        $suffix = strlen($event->output) > 80 ? '...' : '';
        print("       Output: {$preview}{$suffix}\n");
    }
}

function handleStepFinish(StepFinishEvent $event): void {
    print("[STEP] Finished (reason: {$event->reason})");
    if ($event->tokens) {
        print(" - Tokens: {$event->tokens->input} in / {$event->tokens->output} out");
    }
    if ($event->cost > 0) {
        print(" - Cost: \${$event->cost}");
    }
    print("\n");
}

print("\nFinal exit code: {$execResult->exitCode()}\n");

if ($execResult->exitCode() !== 0) {
    print("Error: Command failed\n");
    if ($execResult->stderr()) {
        print("STDERR: " . $execResult->stderr() . "\n");
    }
    exit(1);
}
?>
