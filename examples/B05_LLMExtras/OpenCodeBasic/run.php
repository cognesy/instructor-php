---
title: 'OpenCode CLI - Basic'
docname: 'opencode_basic'
---

## Overview

This example demonstrates how to use the OpenCode CLI integration to execute
simple prompts. The OpenCode component provides a PHP API for invoking the
`opencode run` command in headless mode with JSON streaming output.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

// Step 1: Create a request with a simple prompt
$request = new OpenCodeRequest(
    prompt: 'What is the capital of France? Answer briefly.',
    outputFormat: OutputFormat::Json,
);

print("Sending prompt to OpenCode CLI...\n");
print("Prompt: {$request->prompt()}\n\n");

// Step 2: Build the command specification
$builder = new OpenCodeCommandBuilder();
$spec = $builder->buildRun($request);

// Step 3: Execute the command using sandboxed executor
$executor = SandboxCommandExecutor::forOpenCode();
$execResult = $executor->execute($spec);

// Step 4: Parse the structured response
$parser = new ResponseParser();
$response = $parser->parse($execResult, OutputFormat::Json);

// Step 5: Display results
print("Answer: {$response->messageText()}\n\n");

print("---\n");
print("Session ID: {$response->sessionId()}\n");
print("Exit code: {$response->exitCode()}\n");

if ($response->usage()) {
    print("Tokens: {$response->usage()->input} in / {$response->usage()->output} out\n");
}

if ($response->cost()) {
    print("Cost: \${$response->cost()}\n");
}

if ($execResult->exitCode() !== 0) {
    print("Error: Command failed with exit code {$execResult->exitCode()}\n");
    if ($execResult->stderr()) {
        print("STDERR: " . $execResult->stderr() . "\n");
    }
    exit(1);
}

if ($response->messageText() === '') {
    print("Error: No answer received from OpenCode\n");
    exit(1);
}
?>
```
