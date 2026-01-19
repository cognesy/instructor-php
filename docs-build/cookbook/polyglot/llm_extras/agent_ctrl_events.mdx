<?php declare(strict_types=1);
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;

$agent = AgentCtrl::make(AgentType::OpenCode);

// Option 1: Wiretap observes ALL events
$agent->wiretap(function(AgentEvent $event): void {
    $timestamp = $event->createdAt->format('H:i:s.v');
    echo "[{$timestamp}] EVENT: {$event->name()}\n";
});

// Option 2: Listen to specific events
$agent->onEvent(AgentExecutionStarted::class, function(AgentExecutionStarted $event): void {
    echo "-> Agent {$event->agentType->value} starting\n";
});

$agent->onEvent(AgentTextReceived::class, function(AgentTextReceived $event): void {
    $preview = $event->text;
    if (strlen($event->text) > 50) {
        $preview = substr($event->text, 0, 50) . '...';
        // replace new lines with spaces to keep output clean
        $preview = str_replace("\n", ' ', $preview);
    }
    echo "-> Text: \"{$preview}\"\n";
});

$agent->onEvent(AgentToolUsed::class, function(AgentToolUsed $event): void {
    echo "-> Tool: {$event->tool}\n";
});

$agent->onEvent(AgentExecutionCompleted::class, function(AgentExecutionCompleted $event): void {
    $costLabel = 'n/a';
    if ($event->cost !== null) {
        $costLabel = '$' . number_format($event->cost, 4);
    }
    echo "-> Completed: {$event->toolCallCount} tool calls, cost: {$costLabel}\n";
});

// Execute with streaming to see events in real-time
$response = $agent->executeStreaming('List files in current directory and explain what you see.');

if ($response->isSuccess()) {
    echo "\nFinal response:\n";
    echo $response->text() . "\n";
}
?>
