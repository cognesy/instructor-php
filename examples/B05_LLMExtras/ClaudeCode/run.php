<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

$claudePath = trim((string) shell_exec('command -v claude'));
$stdbufPath = trim((string) shell_exec('command -v stdbuf'));
if ($claudePath === '' || $stdbufPath === '') {
    print("Claude CLI or stdbuf not found. Install Claude Code CLI and coreutils before running this example.\n");
    exit(0);
}

// Step 1: Create a request with a simple prompt
$request = new ClaudeRequest(
    prompt: 'What is the capital of France? Answer briefly.',
    outputFormat: OutputFormat::Json,
    permissionMode: PermissionMode::Plan,
    maxTurns: 1,
    verbose: true,
);

print("Sending prompt to Claude CLI...\n");
print("Prompt: {$request->prompt()}\n\n");

// Step 2: Build the command specification
$builder = new ClaudeCommandBuilder();
$spec = $builder->buildHeadless($request);

// Step 3: Execute the command using sandboxed executor
$executor = SandboxCommandExecutor::default();
$execResult = $executor->execute($spec);

// Step 4: Parse the structured response
$response = (new ResponseParser())->parse($execResult, OutputFormat::Json);

// Step 5: Extract and display the response
print("Response from Claude CLI:\n");
foreach ($response->decoded()->all() as $event) {
    $data = $event->data();
    if (isset($data['message']['content'][0]['text'])) {
        echo $data['message']['content'][0]['text'] . "\n";
    }
}

if ($execResult->exitCode() !== 0) {
    print("Error: Command failed with exit code {$execResult->exitCode()}\n");
    if ($execResult->stderr()) {
        print("STDERR: " . $execResult->stderr() . "\n");
    }
    exit(1);
}
?>
