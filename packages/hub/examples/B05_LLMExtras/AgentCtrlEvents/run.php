<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\AgentCtrl\Event\AgentErrorOccurred;

/**
 * AgentCtrl Events - Logging & Monitoring Example
 *
 * Demonstrates how to use the event system for:
 * - Logging agent lifecycle events
 * - Monitoring tool usage
 * - Tracking execution metrics
 * - Building custom telemetry
 */

print("================================================================================\n");
print("              AgentCtrl Events - Logging & Monitoring Demo                      \n");
print("================================================================================\n\n");

// Create an agent with event monitoring
$agent = AgentCtrl::make(AgentType::OpenCode);

// Option 1: Use wiretap to observe ALL events
print("Setting up event monitoring...\n\n");

$agent->wiretap(function(AgentEvent $event): void {
    // Log every event with timestamp
    $timestamp = $event->createdAt->format('H:i:s.v');
    $eventName = $event->name();
    print("[{$timestamp}] EVENT: {$eventName}\n");
});

// Option 2: Listen to specific event types for targeted monitoring
$agent->onEvent(AgentExecutionStarted::class, function(AgentExecutionStarted $event): void {
    print("  -> Agent {$event->agentType->value} starting execution\n");
    print("     Model: " . ($event->model ?? 'default') . "\n");
});

$agent->onEvent(AgentTextReceived::class, function(AgentTextReceived $event): void {
    $preview = strlen($event->text) > 50
        ? substr($event->text, 0, 50) . '...'
        : $event->text;
    $preview = str_replace("\n", ' ', $preview);
    print("  -> Text chunk: \"{$preview}\"\n");
});

$agent->onEvent(AgentToolUsed::class, function(AgentToolUsed $event): void {
    print("  -> Tool: {$event->tool}\n");
    if (!empty($event->input)) {
        $inputPreview = json_encode($event->input);
        if (strlen($inputPreview) > 60) {
            $inputPreview = substr($inputPreview, 0, 60) . '...';
        }
        print("     Input: {$inputPreview}\n");
    }
});

$agent->onEvent(AgentExecutionCompleted::class, function(AgentExecutionCompleted $event): void {
    print("  -> Execution completed\n");
    print("     Exit code: {$event->exitCode}\n");
    print("     Tool calls: {$event->toolCallCount}\n");
    if ($event->cost !== null) {
        print("     Cost: $" . number_format($event->cost, 4) . "\n");
    }
    if ($event->inputTokens !== null) {
        print("     Tokens: {$event->inputTokens} in / {$event->outputTokens} out\n");
    }
});

$agent->onEvent(AgentErrorOccurred::class, function(AgentErrorOccurred $event): void {
    print("  -> ERROR: {$event->error}\n");
    if ($event->errorClass !== null) {
        print("     Type: {$event->errorClass}\n");
    }
});

print("--------------------------------------------------------------------------------\n");
print("Executing prompt with event monitoring...\n");
print("--------------------------------------------------------------------------------\n\n");

// Execute a streaming request to see events in action
$response = $agent->executeStreaming(
    'List the files in the current directory and explain what you see. Be brief.'
);

print("\n--------------------------------------------------------------------------------\n");
print("Execution finished.\n");
print("--------------------------------------------------------------------------------\n\n");

if ($response->isSuccess()) {
    print("RESULT:\n");
    print(str_repeat("-", 80) . "\n");
    print($response->text() . "\n");
    print(str_repeat("-", 80) . "\n\n");

    if (count($response->toolCalls) > 0) {
        print("TOOL CALLS SUMMARY:\n");
        foreach ($response->toolCalls as $i => $toolCall) {
            $num = $i + 1;
            print("  {$num}. {$toolCall->tool}\n");
        }
        print("\n");
    }
} else {
    print("Request failed with exit code: {$response->exitCode}\n");
}

print("================================================================================\n");
print("Events Demo Complete\n");
print("================================================================================\n");
