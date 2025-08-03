<?php

require_once 'vendor/autoload.php';

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tag\TagInterface;

class ExecutionTag implements TagInterface {
    public function __construct(public readonly string $step) {}
}

// Test the exact scenario from the failing test
$builder = Pipeline::for(10)
    ->withTags(new ExecutionTag('pipeline'))
    ->through(fn($x) => $x * 2);

$pending = $builder->create();

echo "Before execution - checking initial state from PendingExecution:\n";
// Let's check what's in the pending execution's initial state
$reflection = new ReflectionClass($pending);
$initialStateProperty = $reflection->getProperty('initialState');
$initialStateProperty->setAccessible(true);
$rawInitialState = $initialStateProperty->getValue($pending);

$initialTags = $rawInitialState->allTags(ExecutionTag::class);
echo "Raw initial state count: " . count($initialTags) . "\n";
foreach ($initialTags as $tag) {
    echo "Tag: " . $tag->step . "\n";
}

echo "\nAfter execution - getting state from pending:\n";
$executedState = $pending->state();
$executedTags = $executedState->allTags(ExecutionTag::class);
echo "Executed state count: " . count($executedTags) . "\n";
foreach ($executedTags as $tag) {
    echo "Tag: " . $tag->step . "\n";
}

echo "\nAfter map and withTags:\n";
$result = $executedState
    ->map(fn($x) => $x + 5)
    ->withTags(new ExecutionTag('monadic'));

$finalTags = $result->allTags(ExecutionTag::class);
echo "Count: " . count($finalTags) . "\n";
foreach ($finalTags as $tag) {
    echo "Tag: " . $tag->step . "\n";
}
