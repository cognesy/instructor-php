<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Unified Agent - Runtime Switching Example
 *
 * Demonstrates switching between different AI coding agents at runtime.
 * The same prompt is sent to multiple agents to compare their responses.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║          Unified Agent - Runtime Switching Demo                ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

$prompt = 'What design pattern does a class with a static make() method that returns different subclasses based on a parameter typically implement? Answer in one sentence.';

print("PROMPT: {$prompt}\n");
print(str_repeat("─", 68) . "\n\n");

// Test with multiple agents
$agents = [
    'opencode' => 'OpenCode (default model)',
    'claude-code' => 'Claude Code',
    'codex' => 'Codex',
];

foreach ($agents as $agentId => $agentName) {
    print("▶ Testing: {$agentName}\n");

    $startTime = microtime(true);

    try {
        $builder = AgentCtrl::make(AgentType::from($agentId));

        // Claude Code needs maxTurns limit
        if ($agentId === 'claude-code') {
            $builder->withMaxTurns(1);
        }

        $response = $builder->execute($prompt);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if ($response->isSuccess()) {
            print("  ✓ Response ({$elapsed}ms):\n");
            print("    " . wordwrap($response->text(), 60, "\n    ") . "\n");

            if ($response->usage) {
                print("    Tokens: {$response->usage->input} in / {$response->usage->output} out\n");
            }
            if ($response->cost) {
                print("    Cost: $" . number_format($response->cost, 4) . "\n");
            }
        } else {
            print("  ✗ Failed (exit code: {$response->exitCode})\n");
        }
    } catch (Throwable $e) {
        print("  ✗ Error: {$e->getMessage()}\n");
    }

    print("\n");
}

print(str_repeat("─", 68) . "\n");
print("This demonstrates runtime agent switching - the same AgentCtrl\n");
print("API works with different backends (OpenCode, Claude Code, Codex).\n");
