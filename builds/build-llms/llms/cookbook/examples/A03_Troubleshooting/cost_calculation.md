---
title: 'Calculating request cost'
docname: 'cost_calculation'
id: '59c8'
---
## Overview

When using LLM APIs, tracking costs is essential for budgeting and optimization.
Cost calculation is decoupled from usage tracking — you use a calculator to
compute cost from usage and pricing data.

This example demonstrates how to:
1. Define pricing rates ($/1M tokens) with `InferencePricing`
2. Calculate cost using `FlatRateCostCalculator`
3. Compare costs across different models

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Pricing\FlatRateCostCalculator;
use Cognesy\Polyglot\Pricing\Cost;

class User {
    public int $age;
    public string $name;
}

$calculator = new FlatRateCostCalculator();

// Helper to display cost breakdown
function printCostBreakdown(InferenceUsage $usage, InferencePricing $pricing, FlatRateCostCalculator $calculator): void {
    $cost = $calculator->calculate($usage, $pricing);

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
    echo "\nTotal cost: \$" . number_format($cost->total, 6) . "\n";

    echo "\nBreakdown:\n";
    foreach ($cost->breakdown as $category => $amount) {
        printf("  %-12s \$%.6f\n", $category, $amount);
    }
}

echo "CALCULATING COST WITH EXPLICIT PRICING\n";
echo str_repeat("=", 50) . "\n\n";

$text = "Jason is 25 years old and works as an engineer.";

$response = StructuredOutput::using('openai')
    ->with(
        messages: $text,
        responseModel: User::class,
    )->response();

// Define pricing for default model gpt-4.1-nano
$pricing = InferencePricing::fromArray([
    'input' => 0.2,     // $0.2 per 1M input tokens
    'output' => 0.8,    // $0.8 per 1M output tokens
    'cacheRead' => 0.05, // $0.05 per 1M cache read tokens
]);

echo "TEXT: $text\n\n";
printCostBreakdown($response->usage(), $pricing, $calculator);


// COMPARE COSTS ACROSS DIFFERENT MODELS
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
    $pricing = InferencePricing::fromArray($prices);
    $cost = $calculator->calculate($usage, $pricing);
    printf("  %-20s \$%.6f\n", $model, $cost->total);
}

assert($response->value()->name === 'Jason');
assert($response->value()->age === 25);
assert($response->usage()->inputTokens > 0);
assert($response->usage()->outputTokens > 0);
?>
```
