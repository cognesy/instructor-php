<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;

/**
 * Unified Agent - Streaming Example
 *
 * Demonstrates real-time streaming with tool call visibility.
 * The agent searches the codebase and you see each step as it happens.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║            Unified Agent - Streaming Demo                      ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

print("Task: Find and explain the AgentCtrl factory pattern\n");
print(str_repeat("─", 68) . "\n\n");

$toolCalls = [];

$response = AgentCtrl::claudeCode()
    ->withMaxTurns(10)
    ->onText(function (string $text) {
        // Stream text as it arrives - real-time output
        print($text);
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        // Show each tool call as it happens
        $target = $input['pattern'] ?? $input['file_path'] ?? $input['command'] ?? '';
        if (strlen($target) > 40) {
            $target = '...' . substr($target, -37);
        }
        $toolCalls[] = $tool;
        print("\n  ⚡ [{$tool}] {$target}\n");
    })
    ->executeStreaming('Find the AgentCtrl class and explain the make() factory method. Be concise.');

print("\n\n" . str_repeat("─", 68) . "\n");
print("EXECUTION SUMMARY:\n");
print("  Tools used: " . implode(' → ', $toolCalls) . "\n");
print("  Total tool calls: " . count($toolCalls) . "\n");

if ($response->usage) {
    print("  Tokens: {$response->usage->input} in / {$response->usage->output} out\n");
}
if ($response->cost) {
    print("  Cost: $" . number_format($response->cost, 4) . "\n");
}
