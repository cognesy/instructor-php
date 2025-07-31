# Pipeline Performance Benchmarks

This directory contains PHPBench benchmarks for comprehensive performance testing of the Pipeline class and related functionality.

## Overview

These benchmarks complement the existing Pest-based performance tests in `PipelinePerformanceTest.php`, providing more statistically rigorous measurements using PHPBench.

## Benchmark Classes

### 1. `PipelineBench.php`
Main benchmark class testing different Pipeline configurations:
- **Simple Pipeline**: Basic pipeline without middleware or hooks
- **Middleware Pipeline**: Pipeline with timer, retry, and error logging middleware
- **Hooks Pipeline**: Pipeline with before/after hooks
- **Full Pipeline**: Pipeline with both middleware and hooks
- **Raw PHP Baseline**: Direct PHP implementation for comparison
- **ResultChain Baseline**: Using the ResultChain class for comparison

### 2. `ExecutionOrderBench.php`
Tests execution order impact using "provided order" (Raw PHP first):
1. Raw PHP
2. Simple Pipeline  
3. Middleware Pipeline
4. ResultChain

### 3. `ReverseExecutionOrderBench.php`
Tests the same functionality in reverse order:
1. ResultChain
2. Middleware Pipeline
3. Simple Pipeline
4. Raw PHP

### 4. `ShiftedExecutionOrderBench.php`
Tests with shifted order (first element moved to end):
1. Simple Pipeline
2. Middleware Pipeline
3. ResultChain
4. Raw PHP

### 5. `TestMiddleware.php`
Support classes providing test middleware implementations:
- `BenchTimerMiddleware`: Timing measurements
- `BenchRetryMiddleware`: Retry logic
- `BenchErrorLoggerMiddleware`: Error logging
- `BenchDummyLogger`: Dummy logging for hooks

## Configuration

### PHPBench Settings (`phpbench.json`)
- **Iterations**: 5 (statistical samples)
- **Revolutions**: 1000 (iterations per sample)
- **Warmup**: 2 (warmup iterations)
- **Bootstrap**: Uses vendor/autoload.php
- **Path**: tests/Benchmarks

## Running Benchmarks

### Basic Usage

```bash
# Run all benchmarks
vendor/bin/phpbench run tests/Benchmarks --report=default

# Run with detailed aggregate report
vendor/bin/phpbench run tests/Benchmarks --report=aggregate

# Run specific benchmark class
vendor/bin/phpbench run tests/Benchmarks/PipelineBench.php

# Run benchmarks by group
vendor/bin/phpbench run tests/Benchmarks --group=pipeline
vendor/bin/phpbench run tests/Benchmarks --group=baseline
vendor/bin/phpbench run tests/Benchmarks --group=order
```

### Advanced Usage

```bash
# Store results for comparison
vendor/bin/phpbench run tests/Benchmarks --store

# Compare with stored results
vendor/bin/phpbench run tests/Benchmarks --compare=baseline

# Generate HTML report
vendor/bin/phpbench run tests/Benchmarks --report=html --output=html

# Custom iterations/revolutions
vendor/bin/phpbench run tests/Benchmarks --iterations=10 --revs=5000
```

### Execution Order Analysis

```bash
# Run all execution order benchmarks
vendor/bin/phpbench run tests/Benchmarks/ExecutionOrderBench.php tests/Benchmarks/ReverseExecutionOrderBench.php tests/Benchmarks/ShiftedExecutionOrderBench.php --report=aggregate

# Compare execution orders
vendor/bin/phpbench run tests/Benchmarks --group=order --report=aggregate
```

## Groups and Filtering

Available groups for filtering:
- `pipeline`: Pipeline-based tests
- `baseline`: Raw PHP and ResultChain tests
- `simple`: Simple pipeline tests
- `middleware`: Middleware-enabled tests
- `hooks`: Hook-enabled tests
- `full`: Full-featured pipeline tests
- `order`: Execution order tests
- `provided`: Provided order tests
- `reverse`: Reverse order tests
- `shifted`: Shifted order tests
- `raw`: Raw PHP tests
- `resultchain`: ResultChain tests

## Interpreting Results

### Key Metrics
- **time_avg**: Average execution time per revolution
- **mem_peak**: Peak memory usage
- **diff**: Performance difference vs baseline (when comparing)

### Expected Performance Ranking
Based on current measurements:
1. Middleware Pipeline (fastest)
2. Simple Pipeline
3. Hooks Pipeline  
4. Full Pipeline (middleware + hooks)
5. Raw PHP
6. ResultChain (slowest)

### Execution Order Impact
The benchmarks reveal significant performance variations based on execution order, suggesting CPU cache warming effects and memory allocation patterns.

## Integration with Existing Tests

These PHPBench benchmarks complement the existing Pest-based performance tests:
- **Pest tests**: Functional validation and basic performance measurement
- **PHPBench**: Statistical rigor and detailed performance analysis
- **Both**: Comprehensive performance testing strategy

## Future Migration

When PHPBench benchmarks are confirmed to work correctly, they can gradually replace the custom performance measurement logic in the Pest tests while maintaining the functional validation aspects.