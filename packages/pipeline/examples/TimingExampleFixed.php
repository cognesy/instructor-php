<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Stamps\TimingStamp;

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

echo "Result: " . $result->payload() . "\n";

$timings = $result->envelope()->all(TimingStamp::class);
echo "Number of timing stamps: " . count($timings) . "\n";
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
$processedResult = Pipeline::for($validatedResult->payload())
    ->withMiddleware(TimingMiddleware::for('processing'))
    ->through(function($data) {
        usleep(5000); // Simulate processing
        $sum = array_sum($data['numbers']);
        $avg = $sum / count($data['numbers']);
        return ['sum' => $sum, 'average' => $avg, 'count' => count($data['numbers'])];
    })
    ->process();

// Process 3: Formatting
$finalResult = Pipeline::for($processedResult->payload())
    ->withMiddleware(TimingMiddleware::for('formatting'))
    ->through(function($result) {
        usleep(1000); // Simulate formatting
        return "Summary: {$result['count']} numbers, sum={$result['sum']}, avg={$result['average']}";
    })
    ->process();

echo "Final result: " . $finalResult->payload() . "\n";

// Collect all timing information
$allTimings = [
    ...$validatedResult->envelope()->all(TimingStamp::class),
    ...$processedResult->envelope()->all(TimingStamp::class),
    ...$finalResult->envelope()->all(TimingStamp::class)
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
class ErrorAwareTimingMiddleware implements \Cognesy\Pipeline\PipelineMiddlewareInterface
{
    public function handle(\Cognesy\Pipeline\Envelope $envelope, callable $next): \Cognesy\Pipeline\Envelope
    {
        $startTime = microtime(true);
        
        try {
            $nextEnvelope = $next($envelope);
            $endTime = microtime(true);
            
            // Check if result is a failure even without exception
            $success = $nextEnvelope->result()->isSuccess();
            $error = $success ? null : ($nextEnvelope->result()->error()->getMessage() ?? 'Unknown error');
            
            $timingStamp = new TimingStamp(
                startTime: $startTime,
                endTime: $endTime,
                duration: $endTime - $startTime,
                operationName: 'error_aware_operation',
                success: $success,
                error: $error
            );
            
            return $nextEnvelope->with($timingStamp);
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            
            $timingStamp = new TimingStamp(
                startTime: $startTime,
                endTime: $endTime,
                duration: $endTime - $startTime,
                operationName: 'error_aware_operation',
                success: false,
                error: $e->getMessage()
            );
            
            // Return failed envelope with timing
            return $envelope
                ->with($timingStamp)
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

echo "Success: " . ($errorResult->success() ? 'Yes' : 'No') . "\n";
if (!$errorResult->success()) {
    echo "Error: " . $errorResult->failure()->getMessage() . "\n";
}

$timings = $errorResult->envelope()->all(TimingStamp::class);
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

echo "Result: " . json_encode($complexResult->payload()) . "\n";

$timings = $complexResult->envelope()->all(TimingStamp::class);
echo "Total operations measured: " . count($timings) . "\n";
foreach ($timings as $timing) {
    echo "⏱️  " . $timing->summary() . "\n";
}

echo "\n✨ Fixed timing demo completed!\n";