<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2;

// 1. Define the pipeline structure using serializable specifications
use Cognesy\Experimental\Pipeline2\Base\BaseRuntime;
use Cognesy\Experimental\Pipeline2\Contracts\Continuation;

$definition = PipelineDefinition::from(
    // An "around" operator for timing and logging
    Op::around(function (string $payload, Continuation $next) {
        echo ">> Entering timer...\n";
        $start = microtime(true);
        $result = $next->handle($payload); // Proceed with the rest of the chain
        $duration = (float) (microtime(true) - $start);
        $time = round($duration * 1000, 2);
        echo "<< Exiting timer. Duration: " . $time . "ms\n";
        return "[TIMED] " . $result;
    }),
    Op::map( fn(string $s) => trim($s)),
    Op::map(fn(string $s) => strtoupper($s)),
    // This operator only supports payloads that contain "MIDDLEWARE"
    Op::map(function (string $payload, Continuation $next) {
        if (!str_contains($payload, 'MIDDLEWARE')) {
            echo "-> Skipping special op for payload: '{$payload}'\n";
            return $next->handle($payload);
        }
        echo "-> Applying special op!\n";
        return $next->handle(str_replace('MIDDLEWARE', 'MIDDLEWARE_PROCESSED', $payload));
    }),
    Op::map(fn(string $s) => "Processed: {$s}!"),
);

// 2. Set up the runtime
$runtime = BaseRuntime::new();

// 3. Define initial data and terminal action
$input = "  hello middleware  ";
$terminal = fn(string $s) => "--- Finished with: '{$s}' ---\n";

// 4. Start and run the execution
echo "Running Execution...\n";
$execution = $runtime->start($definition, $input, $terminal);
$finalResult = $execution->run();

// The design is now "pipe-ready" for PHP 8.5
// $finalResult = $input |> $runtime->start($definition, $$, $terminal)->run();

echo "\nFinal Result:\n";
print_r($finalResult);