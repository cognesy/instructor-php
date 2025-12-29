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

use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\Agents\Common\Execution\SandboxCommandExecutor;

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
dump($execResult);
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

assert($execResult->exitCode() === 0, 'Command should execute successfully');
?>
```
