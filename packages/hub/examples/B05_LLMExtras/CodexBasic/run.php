---
title: 'OpenAI Codex CLI - Basic'
docname: 'codex_basic'
---

## Overview

This example demonstrates how to use the OpenAI Codex CLI integration to execute
simple prompts. The OpenAICodex component provides a PHP API for invoking the
`codex exec` command in headless mode with sandboxing support.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent\ItemCompletedEvent;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\Item\AgentMessage;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\Auxiliary\Agents\Common\Execution\SandboxCommandExecutor;

// Step 1: Create a request with a simple prompt
$request = new CodexRequest(
    prompt: 'What is the capital of France? Answer briefly.',
    outputFormat: OutputFormat::Json,
    sandboxMode: SandboxMode::ReadOnly,
);

print("Sending prompt to Codex CLI...\n");
print("Prompt: {$request->prompt()}\n\n");

// Step 2: Build the command specification
$builder = new CodexCommandBuilder();
$spec = $builder->buildExec($request);

// Step 3: Execute the command using sandboxed executor
$executor = SandboxCommandExecutor::forCodex();
$execResult = $executor->execute($spec);

// Step 4: Parse the structured response
$parser = new ResponseParser();
$response = $parser->parse($execResult, OutputFormat::Json);

// Step 5: Extract the agent's answer from events
$answer = null;
foreach ($response->decoded()->all() as $object) {
    $event = StreamEvent::fromArray($object->data());

    // Look for completed items that are agent messages
    if ($event instanceof ItemCompletedEvent && $event->item instanceof AgentMessage) {
        $answer = $event->item->text;
    }
}

// Step 6: Display results
print("Answer: {$answer}\n\n");

print("---\n");
print("Thread ID: {$response->threadId()}\n");
print("Exit code: {$response->exitCode()}\n");

if ($response->usage()) {
    print("Tokens: {$response->usage()->inputTokens} in / {$response->usage()->outputTokens} out\n");
}

assert($execResult->exitCode() === 0, 'Command should execute successfully');
assert($answer !== null, 'Should receive an answer from Codex');
?>
```
