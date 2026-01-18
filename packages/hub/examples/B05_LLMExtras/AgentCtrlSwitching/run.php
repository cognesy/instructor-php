<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

$prompt = 'What design pattern does a class with a static make() method implement? Answer in one sentence.';

// Test the same prompt with multiple agents
$agents = [
    'opencode' => 'OpenCode',
    'claude-code' => 'Claude Code',
    'codex' => 'Codex',
];

foreach ($agents as $agentId => $agentName) {
    echo "▶ Testing: {$agentName}\n";

    $startTime = microtime(true);

    try {
        $builder = AgentCtrl::make(AgentType::from($agentId));

        // Apply agent-specific configuration
        if ($agentId === 'claude-code') {
            $builder->withMaxTurns(1);
        }

        $response = $builder->execute($prompt);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if ($response->isSuccess()) {
            echo "  ✓ Response ({$elapsed}ms):\n";
            echo "    " . wordwrap($response->text(), 60, "\n    ") . "\n";

            if ($response->usage) {
                echo "    Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
            }
            if ($response->cost) {
                echo "    Cost: $" . number_format($response->cost, 4) . "\n";
            }
        } else {
            echo "  ✗ Failed (exit code: {$response->exitCode})\n";
        }
    } catch (Throwable $e) {
        echo "  ✗ Error: {$e->getMessage()}\n";
    }

    echo "\n";
}
?>
