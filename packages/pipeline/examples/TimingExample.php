<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tags\TimingTag;

/**
 * Example demonstrating the TimingMiddleware for measuring pipeline execution time.
 */

echo "🕒 Pipeline Timing Middleware Demo\n";
echo "==================================\n\n";

// Example 1: Basic timing
echo "1. Basic Operation Timing\n";
echo "-------------------------\n";

$result = Pipeline::for(100)
    ->withMiddleware(TimingMiddleware::for('basic_math'))
    ->through(function($x) {
        usleep(10000); // Simulate 10ms work
        return $x * 2;
    })
    ->through(function($x) {
        usleep(5000); // Simulate 5ms work  
        return $x + 50;
    })
    ->process();

echo "Result: " . $result->value() . "\n";

$timings = $result->computation()->all(TimingTag::class);
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}

echo "\n";

// Example 2: Multiple operation timing
echo "2. Multiple Operation Timing\n";
echo "----------------------------\n";

$result = Pipeline::for(['numbers' => [1, 2, 3, 4, 5]])
    ->withMiddleware(TimingMiddleware::for('data_validation'))
    ->through(function($data) {
        usleep(2000); // Simulate validation time
        if (!isset($data['numbers']) || !is_array($data['numbers'])) {
            throw new \InvalidArgumentException('Invalid data format');
        }
        return $data;
    })
    ->withMiddleware(TimingMiddleware::for('data_processing'))  
    ->through(function($data) {
        usleep(8000); // Simulate processing time
        $sum = array_sum($data['numbers']);
        $avg = $sum / count($data['numbers']);
        return ['sum' => $sum, 'average' => $avg, 'count' => count($data['numbers'])];
    })
    ->withMiddleware(TimingMiddleware::for('result_formatting'))
    ->through(function($result) {
        usleep(1000); // Simulate formatting time
        return "Summary: {$result['count']} numbers, sum={$result['sum']}, avg={$result['average']}";
    })
    ->process();

echo "Result: " . $result->value() . "\n\n";

$timings = $result->computation()->all(TimingTag::class);
$totalTime = array_sum(array_map(fn($t) => $t->duration, $timings));

echo "Detailed Timing Breakdown:\n";
foreach ($timings as $i => $timing) {
    echo sprintf(
        "  %d. %s: %s (%.1f%% of total)\n",
        $i + 1,
        $timing->operationName ?? 'unnamed',
        $timing->durationFormatted(),
        ($timing->duration / $totalTime) * 100
    );
}
echo "  Total: " . number_format($totalTime * 1000, 2) . "ms\n\n";

// Example 3: Error handling with timing
echo "3. Error Handling with Timing\n";
echo "-----------------------------\n";

$result = Pipeline::for(10)
    ->withMiddleware(TimingMiddleware::for('risky_operation'))
    ->through(function($x) {
        usleep(3000); // Some work before failure
        if ($x < 50) {
            throw new \RuntimeException('Value too small!');
        }
        return $x * 2;
    })
    ->process();

echo "Success: " . ($result->success() ? 'Yes' : 'No') . "\n";
if (!$result->success()) {
    echo "Error: " . $result->failure()->getMessage() . "\n";
}

$timings = $result->computation()->all(TimingTag::class);
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}

echo "\n";

// Example 4: Performance comparison
echo "4. Performance Comparison\n";
echo "------------------------\n";

function runPerformanceTest(string $name, callable $operation, int $iterations = 1000): void
{
    echo "Testing: $name\n";
    
    $results = Pipeline::for($iterations)
        ->withMiddleware(TimingMiddleware::for($name))
        ->through(function($count) use ($operation) {
            $results = [];
            for ($i = 0; $i < $count; $i++) {
                $results[] = $operation($i); 
            }
            return $results;
        })
        ->process();
    
    $timing = $results->computation()->last(TimingTag::class);
    $avgTime = ($timing->duration / $iterations) * 1_000_000; // microseconds per iteration
    
    echo "  Total: " . $timing->durationFormatted() . "\n";
    echo "  Average per iteration: " . number_format($avgTime, 2) . "μs\n";
    echo "  Operations per second: " . number_format($iterations / $timing->duration, 0) . "\n\n";
}

runPerformanceTest('Simple Math', fn($i) => $i * 2 + 1);
runPerformanceTest('String Operations', fn($i) => strtoupper("item_$i"));
runPerformanceTest('Array Operations', fn($i) => array_fill(0, 3, $i));

// Example 5: Timing analysis helpers
echo "5. Timing Analysis\n";
echo "------------------\n";

$complexResult = Pipeline::for(range(1, 100))
    ->withMiddleware(TimingMiddleware::for('input_processing'))
    ->through(function($numbers) {
        usleep(2000);
        return array_filter($numbers, fn($n) => $n % 2 === 0);
    })
    ->withMiddleware(TimingMiddleware::for('computation'))
    ->through(function($evenNumbers) {
        usleep(5000);
        return array_map(fn($n) => $n ** 2, $evenNumbers);
    })
    ->withMiddleware(TimingMiddleware::for('aggregation'))
    ->through(function($squares) {
        usleep(1000);
        return [
            'count' => count($squares),
            'sum' => array_sum($squares),
            'max' => max($squares),
            'min' => min($squares)
        ];
    })
    ->process();

$computation = $complexResult->computation();
$timings = $computation->all(TimingTag::class);

echo "Pipeline Analysis:\n";
echo "  Total operations: " . count($timings) . "\n";
echo "  All successful: " . (array_reduce($timings, fn($acc, $t) => $acc && $t->isSuccess(), true) ? 'Yes' : 'No') . "\n";

// Find slowest operation
$slowest = array_reduce($timings, fn($max, $t) => ($max === null || $t->duration > $max->duration) ? $t : $max);
echo "  Slowest operation: " . $slowest->operationName . " (" . $slowest->durationFormatted() . ")\n";

// Find fastest operation  
$fastest = array_reduce($timings, fn($min, $t) => ($min === null || $t->duration < $min->duration) ? $t : $min);
echo "  Fastest operation: " . $fastest->operationName . " (" . $fastest->durationFormatted() . ")\n";

$totalDuration = array_sum(array_map(fn($t) => $t->duration, $timings));
echo "  Total execution time: " . number_format($totalDuration * 1000, 2) . "ms\n";

echo "\n✨ Timing demo completed!\n";