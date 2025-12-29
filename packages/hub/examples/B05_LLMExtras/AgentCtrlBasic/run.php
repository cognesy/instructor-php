<?php
require 'examples/boot.php';

use Cognesy\Auxiliary\Agents\Unified\AgentCtrl;
use Cognesy\Auxiliary\Agents\Unified\Enum\AgentType;

/**
 * Unified Agent - Basic Example
 *
 * Demonstrates the simplest use of AgentCtrl to execute a prompt
 * against a CLI-based code agent.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║              Unified Agent - Basic Usage Demo                  ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

// Simple prompt execution
print("Sending prompt to OpenCode agent...\n\n");

$response = AgentCtrl::make(AgentType::OpenCode)
    ->execute('Explain the SOLID principles in software design. List each principle with a one-line explanation.');

if ($response->isSuccess()) {
    print("RESPONSE:\n");
    print(str_repeat("─", 68) . "\n");
    print($response->text() . "\n");
    print(str_repeat("─", 68) . "\n\n");

    print("STATS:\n");
    print("  Agent: {$response->agentType->value}\n");

    if ($response->sessionId) {
        print("  Session: {$response->sessionId}\n");
    }
    if ($response->usage) {
        print("  Tokens: {$response->usage->input} input, {$response->usage->output} output\n");
    }
    if ($response->cost) {
        print("  Cost: $" . number_format($response->cost, 4) . "\n");
    }
} else {
    print("ERROR: Request failed with exit code {$response->exitCode}\n");
}
