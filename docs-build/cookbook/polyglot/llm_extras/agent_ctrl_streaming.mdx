<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;

$toolCalls = [];

$response = AgentCtrl::claudeCode()
    ->withMaxTurns(10)
    ->onText(function (string $text) {
        // Stream text as it arrives
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        // Show each tool call as it happens
        $target = $input['pattern'] ?? $input['file_path'] ?? $input['command'] ?? '';
        if (strlen($target) > 40) {
            $target = '...' . substr($target, -37);
        }
        $toolCalls[] = $tool;
        echo "\n  ⚡ [{$tool}] {$target}\n";
    })
    ->executeStreaming('Find the AgentCtrl class and explain the make() factory method. Be concise.');

// Summary after execution completes
echo "\n\nEXECUTION SUMMARY:\n";
echo "  Tools used: " . implode(' → ', $toolCalls) . "\n";
echo "  Total tool calls: " . count($toolCalls) . "\n";

if ($response->usage) {
    echo "  Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
}
if ($response->cost) {
    echo "  Cost: $" . number_format($response->cost, 4) . "\n";
}
?>
