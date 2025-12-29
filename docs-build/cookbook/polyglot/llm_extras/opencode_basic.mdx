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
<?php
require 'examples/boot.php';

use Cognesy\Auxiliary\Agents\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\Auxiliary\Agents\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\Auxiliary\Agents\OpenCode\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\Common\Execution\SandboxCommandExecutor;

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

assert($execResult->exitCode() === 0, 'Command should execute successfully');
assert($response->messageText() !== '', 'Should receive an answer from OpenCode');
?>
```
