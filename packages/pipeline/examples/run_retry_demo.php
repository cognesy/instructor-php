<?php declare(strict_types=1);

/**
 * Demo runner for Pipeline Retry Middleware
 * 
 * This script demonstrates a comprehensive retry system implementation
 * using Pipeline middleware and state tags.
 */

use examples\RetryExampleTest;

echo "🚀 Pipeline Retry System Demo\n";
echo "=============================\n";

// Check if we should run tests or examples
$runTests = in_array('--test', $argv ?? []);
$runExamples = in_array('--examples', $argv ?? []) || !$runTests;

if ($runTests) {
    echo "Running comprehensive tests...\n";
    require_once __DIR__ . '/RetryExampleTest.php';
    RetryExampleTest::runAllTests();
}

if ($runExamples) {
    echo "Running interactive examples...\n";
    require_once __DIR__ . '/RetryExample.php';
    
    // Run the examples (they're already set to execute when included)
}

if (!$runTests && !$runExamples) {
    echo "\nUsage:\n";
    echo "  php run_retry_demo.php --examples  # Run interactive examples\n";
    echo "  php run_retry_demo.php --test      # Run test suite\n";
    echo "  php run_retry_demo.php             # Run examples (default)\n";
}