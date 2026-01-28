---
title: 'Calculating request cost'
docname: 'cost_calculation'
---

## Overview

When using LLM APIs, tracking costs is essential for budgeting and optimization.
Instructor supports automatic cost calculation based on token usage and pricing
configuration in your LLM presets.

This example demonstrates how to:
1. Configure pricing in LLM config ($/1M tokens)
2. Calculate cost after a request using `Usage::calculateCost()`

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Data\Usage;

class User {
    public int $age;
    public string $name;
}

// Helper to display cost breakdown
function printCostBreakdown(Usage $usage, Pricing $pricing): void {
    echo "Token Usage:\n";
    echo "  Input tokens:     {$usage->inputTokens}\n";
    echo "  Output tokens:    {$usage->outputTokens}\n";
    echo "  Cache read:       {$usage->cacheReadTokens}\n";
    echo "  Cache write:      {$usage->cacheWriteTokens}\n";
    echo "  Reasoning:        {$usage->reasoningTokens}\n";
    echo "\nPricing ($/1M tokens):\n";
    echo "  Input:      \${$pricing->inputPerMToken}\n";
    echo "  Output:     \${$pricing->outputPerMToken}\n";
    echo "  Cache read: \${$pricing->cacheReadPerMToken}\n";
    echo "\nTotal cost: \$" . number_format($usage->calculateCost(), 6) . "\n";
}

// OPTION 1: Configure pricing in LLM config preset
// In your config/llm.php, add pricing to your preset:
//
// 'openrouter-claude' => [
//     'driver' => 'openrouter',
//     'model' => 'anthropic/claude-3.5-sonnet',
//     'pricing' => [
//         'input' => 3.0,    // $3 per 1M input tokens
//         'output' => 15.0,  // $15 per 1M output tokens
//         // cacheRead, cacheWrite, reasoning default to input price if not set
//     ],
// ],
//
// Then calculateCost() works automatically:
//
// $response = (new StructuredOutput)
//     ->using('openrouter-claude')
//     ->with(messages: $text, responseModel: User::class)
//     ->response();
// $cost = $response->usage()->calculateCost();

// OPTION 2: Calculate cost manually with explicit Pricing
echo "CALCULATING COST WITH EXPLICIT PRICING\n";
echo str_repeat("=", 50) . "\n\n";

$text = "Jason is 25 years old and works as an engineer.";

$response = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: User::class,
    )->response();

// Define pricing for Claude 3.5 Sonnet ($/1M tokens)
$pricing = Pricing::fromArray([
    'input' => 3.0,     // $3 per 1M input tokens
    'output' => 15.0,   // $15 per 1M output tokens
]);

echo "TEXT: $text\n\n";
printCostBreakdown($response->usage(), $pricing);

// You can also attach pricing to usage for later calculation
$usageWithPricing = $response->usage()->withPricing($pricing);
echo "\nCost via stored pricing: \$" . number_format($usageWithPricing->calculateCost(), 6) . "\n";


// OPTION 3: Compare costs across different models
echo "\n\n" . str_repeat("=", 50) . "\n";
echo "COST COMPARISON ACROSS MODELS\n";
echo str_repeat("=", 50) . "\n\n";

$usage = $response->usage();

$models = [
    'GPT-4o' => ['input' => 2.50, 'output' => 10.0],
    'GPT-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    'Claude 3.5 Sonnet' => ['input' => 3.0, 'output' => 15.0],
    'Claude 3.5 Haiku' => ['input' => 0.80, 'output' => 4.0],
    'Gemini 2.0 Flash' => ['input' => 0.10, 'output' => 0.40],
];

echo "For {$usage->inputTokens} input + {$usage->outputTokens} output tokens:\n\n";
foreach ($models as $model => $prices) {
    $pricing = Pricing::fromArray($prices);
    $cost = $usage->calculateCost($pricing);
    printf("  %-20s \$%.6f\n", $model, $cost);
}
?>
```
