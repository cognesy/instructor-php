---
title: 'Claude Code CLI - Basic'
docname: 'claude_code_basic'
---

## Overview

This example demonstrates how to use the Claude Code CLI integration to execute
simple prompts. The ClaudeCodeCli component provides a PHP API for invoking the
`claude` CLI in headless mode with sandboxing support.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

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
```
