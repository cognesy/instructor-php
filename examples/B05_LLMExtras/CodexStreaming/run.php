<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ThreadStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\TurnStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\TurnCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ItemStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ItemCompletedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\AgentMessage;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\CommandExecution;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

$codexPath = trim((string) shell_exec('command -v codex'));
$stdbufPath = trim((string) shell_exec('command -v stdbuf'));
if ($codexPath === '' || $stdbufPath === '') {
    print("Codex CLI or stdbuf not found. Install Codex CLI and coreutils before running this example.\n");
    exit(0);
}

// Create a request - must specify working directory for sandbox access
$request = new CodexRequest(
    prompt: 'List the files in the current directory and explain what you see.',
    outputFormat: OutputFormat::Json,
    sandboxMode: SandboxMode::ReadOnly,
    workingDirectory: getcwd(),
);

print("Streaming Codex CLI output...\n");
print("Prompt: {$request->prompt()}\n\n");

// Build command
$builder = new CodexCommandBuilder();
$spec = $builder->buildExec($request);

// Execute with streaming callback
$executor = SandboxCommandExecutor::forCodex();

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
            $event instanceof ThreadStartedEvent => print("[THREAD] Started: {$event->threadId}\n"),
            $event instanceof TurnStartedEvent => print("[TURN] Started\n"),
            $event instanceof TurnCompletedEvent => handleTurnCompleted($event),
            $event instanceof ItemStartedEvent => handleItemStarted($event),
            $event instanceof ItemCompletedEvent => handleItemCompleted($event),
            default => print("[{$event->type()}]\n"),
        };
    }
});

function handleTurnCompleted(TurnCompletedEvent $event): void {
    print("[TURN] Completed");
    if ($event->usage) {
        print(" (input: {$event->usage->inputTokens}, output: {$event->usage->outputTokens})");
    }
    print("\n");
}

function handleItemStarted(ItemStartedEvent $event): void {
    $item = $event->item;
    print("[ITEM] Started: {$item->itemType()} ({$item->id})\n");

    if ($item instanceof CommandExecution) {
        print("       Command: {$item->command}\n");
    }
}

function handleItemCompleted(ItemCompletedEvent $event): void {
    $item = $event->item;
    print("[ITEM] Completed: {$item->itemType()} ({$item->id})\n");

    if ($item instanceof AgentMessage) {
        print("       Message: " . substr($item->text, 0, 100) . "...\n");
    } elseif ($item instanceof CommandExecution && $item->output !== null) {
        $preview = substr($item->output, 0, 100);
        print("       Output: {$preview}...\n");
    }
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
