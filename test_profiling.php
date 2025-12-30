<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Events\Event;
use Cognesy\Events\EventBusResolver;

/**
 * Test script to verify AgentCtrl profiling events work correctly.
 * This will show timing breakdown for Claude Code execution.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║              AgentCtrl Profiling Test                         ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

// Create a simple event listener to capture timing events
$events = [];
$startTime = microtime(true);

$listener = function (Event $event) use (&$events, $startTime): void {
    $relativeTime = (microtime(true) - $startTime) * 1000; // ms since test start
    $events[] = [
        'time' => $relativeTime,
        'event' => $event,
        'data' => $event->data(),
    ];
};

// Get the event bus and register our listener
// We need to access the event bus from EventBusResolver since the builder doesn't expose it
$eventBus = EventBusResolver::default();

// Use wiretap to listen to all events
$eventBus->wiretap($listener);

print("🔄 Executing Claude Code with profiling...\n\n");

$prompt = 'What is 3 + 3? Answer with just the number.';

try {
    $testStart = microtime(true);

    $response = AgentCtrl::claudeCode()
        ->withMaxTurns(1)
        ->verbose(false) // Reduce processing overhead
        ->execute($prompt);

    $testDuration = (microtime(true) - $testStart) * 1000;

    print(sprintf("✅ Execution completed in %.1fms\n", $testDuration));
    print("📝 Response: " . $response->text() . "\n");
    print("🔧 Exit Code: " . ($response->exitCode ?? 'N/A') . "\n\n");

    // Display profiling results
    print("📊 Profiling Results:\n");
    print(str_repeat("─", 68) . "\n");

    if (empty($events)) {
        print("⚠️  No profiling events captured\n");
        print("   This might indicate the events system is not working properly\n");
    } else {
        $previousTime = 0;
        foreach ($events as $i => $eventInfo) {
            $event = $eventInfo['event'];
            $relativeTime = $eventInfo['time'];
            $deltaTime = $relativeTime - $previousTime;

            $eventName = (new ReflectionClass($event))->getShortName();
            $eventData = $eventInfo['data'];

            // Format event-specific details
            switch($eventName) {
                case 'RequestBuilt':
                    $details = "({$eventData['buildDurationMs']}ms)";
                    break;
                case 'CommandSpecCreated':
                    $details = "({$eventData['argvCount']} args, {$eventData['commandDurationMs']}ms)";
                    break;
                case 'SandboxPolicyConfigured':
                    $details = "({$eventData['driver']}, {$eventData['configureDurationMs']}ms)";
                    break;
                case 'SandboxInitialized':
                    $details = "({$eventData['driver']}, {$eventData['initializationDurationMs']}ms)";
                    break;
                case 'SandboxReady':
                    $details = "({$eventData['totalSetupDurationMs']}ms total)";
                    break;
                case 'ProcessExecutionStarted':
                    $details = "({$eventData['commandCount']} commands)";
                    break;
                case 'ExecutionAttempted':
                    $details = "attempt #{$eventData['attemptNumber']} ({$eventData['executionDurationMs']}ms)";
                    break;
                case 'ProcessExecutionCompleted':
                    $details = "({$eventData['totalAttempts']} attempts, {$eventData['totalExecutionDurationMs']}ms)";
                    break;
                case 'StreamChunkProcessed':
                    $details = "chunk #{$eventData['chunkNumber']} ({$eventData['chunkSize']} bytes, {$eventData['processingDurationMs']}ms)";
                    break;
                case 'StreamProcessingCompleted':
                    $details = "({$eventData['totalChunks']} chunks, {$eventData['totalDurationMs']}ms)";
                    break;
                case 'ResponseParsingStarted':
                    $details = "({$eventData['responseSize']} bytes, {$eventData['format']})";
                    break;
                case 'ResponseDataExtracted':
                    $details = "({$eventData['eventCount']} events, {$eventData['toolUseCount']} tools, {$eventData['extractDurationMs']}ms)";
                    break;
                case 'ResponseParsingCompleted':
                    $details = "({$eventData['totalDurationMs']}ms)";
                    break;
                case 'AgentExecutionStarted':
                    $details = "(prompt: " . substr($eventData['prompt'], 0, 30) . "...)";
                    break;
                case 'AgentExecutionCompleted':
                    $details = "(exit: {$eventData['exitCode']})";
                    break;
                default:
                    $details = json_encode($eventData);
                    break;
            }

            $timeInfo = sprintf("%6.1f", $relativeTime);
            $deltaInfo = $i > 0 ? sprintf("+%5.1f", $deltaTime) : "      ";

            print(sprintf(
                "%2d. %sms %s │ %-25s %s\n",
                $i + 1,
                $timeInfo,
                $deltaInfo,
                $eventName,
                $details
            ));

            $previousTime = $relativeTime;
        }

        print(str_repeat("─", 68) . "\n");
        print(sprintf("Total: %.1fms (%d events captured)\n", $relativeTime, count($events)));

        // Calculate phase durations
        $phases = [
            'Setup' => ['RequestBuilt', 'CommandSpecCreated'],
            'Sandbox' => ['SandboxPolicyConfigured', 'SandboxInitialized', 'SandboxReady'],
            'Execution' => ['ProcessExecutionStarted', 'ExecutionAttempted', 'ProcessExecutionCompleted'],
            'Response' => ['ResponseParsingStarted', 'ResponseDataExtracted', 'ResponseParsingCompleted'],
        ];

        print("\n🔍 Phase Breakdown:\n");
        foreach ($phases as $phaseName => $phaseEvents) {
            $phaseStart = null;
            $phaseEnd = null;

            foreach ($events as $eventInfo) {
                $eventName = (new ReflectionClass($eventInfo['event']))->getShortName();
                if (in_array($eventName, $phaseEvents)) {
                    if ($phaseStart === null) {
                        $phaseStart = $eventInfo['time'];
                    }
                    $phaseEnd = $eventInfo['time'];
                }
            }

            if ($phaseStart !== null && $phaseEnd !== null) {
                $phaseDuration = $phaseEnd - $phaseStart;
                print(sprintf("   %-10s: %6.1fms\n", $phaseName, $phaseDuration));
            }
        }
    }

} catch (Exception $e) {
    print("❌ Error during execution: {$e->getMessage()}\n");
    print("Events captured before error: " . count($events) . "\n");
}

print("\n" . str_repeat("─", 68) . "\n");
print("🎯 Profiling test complete!\n");