<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tag\TimingTag;

echo "🕒 Pipeline Timing Middleware Demo (Fixed)\n";
echo "==========================================\n\n";

// Example 1: Correct way - Single timing middleware for entire pipeline
echo "1. Timing Entire Pipeline\n";
echo "-------------------------\n";

$result = Pipeline::for(100)
    ->withMiddleware(TimingMiddleware::for('complete_pipeline'))
    ->through(function($x) {
        usleep(5000); // 5ms work
        return $x * 2;
    })
    ->through(function($x) {
        usleep(3000); // 3ms work  
        return $x + 50;
    })
    ->process();

echo "Result: " . $result->value() . "\n";

$timings = $result->computation()->all(TimingTag::class);
echo "Number of timing tags: " . count($timings) . "\n";
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}
echo "\n";

// Example 2: Per-processor timing (separate pipelines)
echo "2. Individual Processor Timing\n";
echo "------------------------------\n";

$data = ['numbers' => [1, 2, 3, 4, 5]];

// Process 1: Validation
$validatedResult = Pipeline::for($data)
    ->withMiddleware(TimingMiddleware::for('validation'))
    ->through(function($data) {
        usleep(2000); // Simulate validation
        if (!isset($data['numbers']) || !is_array($data['numbers'])) {
            throw new \InvalidArgumentException('Invalid data format');
        }
        return $data;
    })
    ->process();

// Process 2: Processing  
$processedResult = Pipeline::for($validatedResult->value())
    ->withMiddleware(TimingMiddleware::for('processing'))
    ->through(function($data) {
        usleep(5000); // Simulate processing
        $sum = array_sum($data['numbers']);
        $avg = $sum / count($data['numbers']);
        return ['sum' => $sum, 'average' => $avg, 'count' => count($data['numbers'])];
    })
    ->process();

// Process 3: Formatting
$finalResult = Pipeline::for($processedResult->value())
    ->withMiddleware(TimingMiddleware::for('formatting'))
    ->through(function($result) {
        usleep(1000); // Simulate formatting
        return "Summary: {$result['count']} numbers, sum={$result['sum']}, avg={$result['average']}";
    })
    ->process();

echo "Final result: " . $finalResult->value() . "\n";

// Collect all timing information
$allTimings = [
    ...$validatedResult->computation()->all(TimingTag::class),
    ...$processedResult->computation()->all(TimingTag::class),
    ...$finalResult->computation()->all(TimingTag::class)
];

echo "\nTiming Breakdown:\n";
foreach ($allTimings as $timing) {
    echo "  " . $timing->summary() . "\n";
}

$totalTime = array_sum(array_map(fn($t) => $t->duration, $allTimings));
echo "Total time: " . number_format($totalTime * 1000, 2) . "ms\n\n";

// Example 3: Error handling - middleware sees the exception
echo "3. Error Handling (Custom Middleware)\n";
echo "------------------------------------\n";

// Create a custom error-aware timing middleware
class ErrorAwareTimingMiddleware implements \Cognesy\Pipeline\Middleware\PipelineMiddlewareInterface
{
    public function handle(\Cognesy\Pipeline\Computation $computation, callable $next): \Cognesy\Pipeline\Computation
    {
        $startTime = microtime(true);
        
        try {
            $nextComputation = $next($computation);
            $endTime = microtime(true);
            
            // Check if result is a failure even without exception
            $success = $nextComputation->result()->isSuccess();
            $error = $success ? null : ($nextComputation->result()->error()->getMessage() ?? 'Unknown error');
            
            $timingTag = new TimingTag(
                startTime: $startTime,
                endTime: $endTime,
                duration: $endTime - $startTime,
                operationName: 'error_aware_operation',
                success: $success,
                error: $error
            );
            
            return $nextComputation->with($timingTag);
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            
            $timingTag = new TimingTag(
                startTime: $startTime,
                endTime: $endTime,
                duration: $endTime - $startTime,
                operationName: 'error_aware_operation',
                success: false,
                error: $e->getMessage()
            );
            
            // Return failed computation with timing
            return $computation
                ->with($timingTag)
                ->withResult(\Cognesy\Utils\Result\Result::failure($e));
        }
    }
}

$errorResult = Pipeline::for(10)
    ->withMiddleware(new ErrorAwareTimingMiddleware())
    ->through(function($x) {
        usleep(2000); // Some work before failure
        if ($x < 50) {
            throw new \RuntimeException('Value too small!');
        }
        return $x * 2;
    })
    ->process();

echo "Success: " . ($errorResult->isSuccess() ? 'Yes' : 'No') . "\n";
if (!$errorResult->isSuccess()) {
    echo "Error: " . $errorResult->exception()->getMessage() . "\n";
}

$timings = $errorResult->computation()->all(TimingTag::class);
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}
echo "\n";

// Example 4: Multiple operations with single timing
echo "4. Complex Pipeline with Single Timing\n";
echo "--------------------------------------\n";

$complexResult = Pipeline::for(range(1, 10))
    ->withMiddleware(TimingMiddleware::for('complex_processing'))
    ->through(function($numbers) {
        usleep(1000);
        return array_filter($numbers, fn($n) => $n % 2 === 0);
    })
    ->through(function($evenNumbers) {
        usleep(2000);
        return array_map(fn($n) => $n ** 2, $evenNumbers);
    })
    ->through(function($squares) {
        usleep(500);
        return [
            'count' => count($squares),
            'sum' => array_sum($squares),
            'max' => max($squares),
            'min' => min($squares)
        ];
    })
    ->process();

echo "Result: " . json_encode($complexResult->value()) . "\n";

$timings = $complexResult->computation()->all(TimingTag::class);
echo "Total operations measured: " . count($timings) . "\n";
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}

echo "\n✨ Fixed timing demo completed!\n";