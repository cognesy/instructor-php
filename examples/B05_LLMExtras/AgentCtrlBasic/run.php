<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Execute a prompt against OpenCode agent
$response = AgentCtrl::make(AgentType::OpenCode)
    ->execute('Explain the SOLID principles in software design. List each principle with a one-line explanation.');

// Check if successful
if ($response->isSuccess()) {
    echo "RESPONSE:\n";
    echo $response->text() . "\n\n";

    // Access metadata
    echo "STATS:\n";
    echo "  Agent: {$response->agentType->value}\n";

    if ($response->sessionId) {
        echo "  Session: {$response->sessionId}\n";
    }
    if ($response->usage) {
        echo "  Tokens: {$response->usage->input} input, {$response->usage->output} output\n";
    }
    if ($response->cost) {
        echo "  Cost: $" . number_format($response->cost, 4) . "\n";
    }
} else {
    echo "ERROR: Request failed with exit code {$response->exitCode}\n";
}
?>
