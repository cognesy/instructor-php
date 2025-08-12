<?php

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

test('nested pipelines - pipeline containing other pipelines as steps', function () {
    // Create first sub-pipeline using builder API: multiply by 2, then add 10
    $pipeline1 = Pipeline::builder()
        ->through(fn($x) => $x * 2)
        ->through(fn($x) => $x + 10)
        ->create();

    // Create second sub-pipeline using builder API: divide by 3, then subtract 5
    $pipeline2 = Pipeline::builder()
        ->through(fn($x) => $x / 3)
        ->through(fn($x) => $x - 5)
        ->create();

    // Create main pipeline that uses the two sub-pipelines as processing steps
    $mainPipeline = Pipeline::builder()
        ->through(fn($x) => $x + 1)          // Start: x + 1
        ->throughOperator($pipeline1)        // Step 1: (x + 1) * 2 + 10
        ->through(fn($x) => $x * 3)          // Middle: result * 3
        ->throughOperator($pipeline2)        // Step 2: result / 3 - 5
        ->through(fn($x) => round($x, 2))    // Final: round result
        ->create();

    // Test with input 5:
    // 5 + 1 = 6
    // Pipeline1: 6 * 2 + 10 = 22
    // 22 * 3 = 66
    // Pipeline2: 66 / 3 - 5 = 22 - 5 = 17
    // round(17) = 17
    expect($mainPipeline
        ->executeWith(ProcessingState::with(null))
        ->for(5)
        ->value()
    )->toBe(17.0);

    // Test with input 10:
    // 10 + 1 = 11
    // Pipeline1: 11 * 2 + 10 = 32
    // 32 * 3 = 96
    // Pipeline2: 96 / 3 - 5 = 32 - 5 = 27
    // round(27) = 27
    expect($mainPipeline
        ->executeWith(ProcessingState::with(null))
        ->for(10)
        ->value()
    )->toBe(27.0);
});
