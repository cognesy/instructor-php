# Result-Aware Stream Components

This package provides transducers, sinks, and decorators for handling `Result` monads in stream processing pipelines. It enables functional error handling without exceptions while maintaining single-pass stream processing.

## Overview

**Result-aware components** allow you to:
- Build resilient pipelines where errors are first-class citizens
- Compose operations that may fail without try/catch blocks
- Separate success and failure paths declaratively
- Aggregate successes and failures independently
- Enable graceful degradation with recovery strategies

## Components

### Transducers (12)

Transform Results in the stream:

- **MapResult** - Transform success values
- **ThenResult** - Chain Result-returning operations (flatMap)
- **EnsureResult** - Add validation guards
- **RecoverResult** - Provide fallback values for failures
- **MapErrorResult** - Transform error messages
- **TapResult** - Execute side effects based on Result state
- **FilterSuccess** - Keep only successful Results
- **FilterFailure** - Keep only failed Results
- **UnwrapResult** - Extract success values, skip failures
- **UnwrapResultOr** - Extract values with defaults for failures
- **WrapResult** - Wrap non-Result values in Success
- **PartitionResults** - Split into successes and failures

### Sinks (7)

Terminal operations for Result streams:

- **FirstSuccessReducer** - Find first success (early termination)
- **AllSuccessReducer** - Verify all succeed (fail fast)
- **CountSuccessReducer** - Count successful Results
- **CountFailureReducer** - Count failed Results
- **CollectErrorsReducer** - Aggregate error messages
- **ToResultReducer** - Aggregate to single Result
- **PartitionResultsReducer** - Separate successes and failures

### Decorators (3)

Wrap existing reducers with Result awareness:

- **OnSuccessReducer** - Process only successful Results
- **OnFailureReducer** - Process only failed Results
- **ResultAwareReducer** - Custom handlers for both states

## Quick Examples

### Basic Transformation

```php
use Cognesy\Stream\Transform\Result\Transducers\{FilterSuccess};use Cognesy\Stream\Transform\Result\Transducers\MapResult;use Cognesy\Stream\Transform\Result\Transducers\UnwrapResult;use Cognesy\Stream\Transformation;use Cognesy\Utils\Result\Result;

// Transform and filter successful operations
$values = Transformation::define(
    new MapResult(fn($x) => $x * 2),
    new FilterSuccess(),
    new UnwrapResult()
)->executeOn([
    Result::success(5),
    Result::failure('error'),
    Result::success(10),
]);
// Result: [10, 20]
```

### Chaining with ThenResult

```php
use Cognesy\Stream\Transform\Result\Transducers\ThenResult;

// Chain operations that return Results
$users = Transformation::define(
    new ThenResult(fn($id) => $repository->findById($id)),
    new ThenResult(fn($user) => validateUser($user)),
    new ThenResult(fn($user) => enrichUserData($user))
)->executeOn([1, 2, 3]);
```

### Validation Pipeline

```php
use Cognesy\Stream\Transform\Result\Transducers\{EnsureResult};use Cognesy\Stream\Transform\Result\Transducers\WrapResult;

$validated = Transformation::define(
    new WrapResult(),
    new EnsureResult(
        predicate: fn($data) => isset($data['email']),
        errorMessage: 'Email is required'
    ),
    new EnsureResult(
        predicate: fn($data) => filter_var($data['email'], FILTER_VALIDATE_EMAIL),
        errorMessage: 'Invalid email format'
    )
)->executeOn($userInputs);
```

### Error Recovery

```php
use Cognesy\Stream\Transform\Result\Transducers\{MapResult};use Cognesy\Stream\Transform\Result\Transducers\RecoverResult;

$withFallback = Transformation::define(
    new MapResult(fn($url) => Result::try(fn() => fetchApiData($url))),
    new RecoverResult(fn($error) => getCachedData())
)->executeOn($urls);
```

### Side Effects

```php
use Cognesy\Stream\Transform\Result\Transducers\TapResult;

$logged = Transformation::define(
    new TapResult(
        onSuccess: fn($val) => logger()->info("Success: $val"),
        onFailure: fn($err) => logger()->error("Error: $err")
    )
)->executeOn($operations);
```

### Aggregation

```php
use Cognesy\Stream\Transform\Result\Sinks\{PartitionResultsReducer};use Cognesy\Stream\Transform\Result\Sinks\ToResultReducer;

// Single Result containing all successes or first failure
$result = Transformation::define()
    ->withInput($operations)
    ->withSink(new ToResultReducer())
    ->execute();
// Result::success([...]) or Result::failure('...')

// Separate successes and failures
$partition = Transformation::define()
    ->withInput($operations)
    ->withSink(new PartitionResultsReducer())
    ->execute();
// ['successes' => [...], 'failures' => [...]]
```

### Early Termination

```php
use Cognesy\Stream\Transform\Result\Sinks\{FirstSuccessReducer};use Cognesy\Stream\Transform\Result\Sinks\AllSuccessReducer;

// Find first success, skip rest
$first = Transformation::define()
    ->withInput($attempts)
    ->withSink(new FirstSuccessReducer())
    ->execute();

// Verify all succeed, fail fast on first error
$allValid = Transformation::define()
    ->withInput($validations)
    ->withSink(new AllSuccessReducer())
    ->execute();
```

### Decorator Pattern

```php
use Cognesy\Stream\Sinks\{Stats\SumReducer,ToArrayReducer};use Cognesy\Stream\Transform\Result\Decorators\OnSuccessReducer;

// Sum only successful values
$sum = Transformation::define()
    ->withInput([Result::success(1), Result::failure('e'), Result::success(2)])
    ->withSink(new OnSuccessReducer(new SumReducer()))
    ->execute();
// 3

// Collect only error messages
$errors = Transformation::define()
    ->withInput($results)
    ->withSink(new OnFailureReducer(new ToArrayReducer()))
    ->execute();
```

## Real-World Patterns

### API Integration

```php
use Cognesy\Stream\Transform\Result\Transducers\{EnsureResult};use Cognesy\Stream\Transform\Result\Transducers\MapResult;use Cognesy\Stream\Transform\Result\Transducers\RecoverResult;use Cognesy\Stream\Transform\Result\Transducers\UnwrapResult;use Cognesy\Stream\TransformationStream;

$users = TransformationStream::from($apiResponses)
    ->through(
        new MapResult(fn($resp) => json_decode($resp->body, true)),
        new EnsureResult(
            predicate: fn($data) => isset($data['id'], $data['email']),
            errorMessage: 'Invalid user data'
        ),
        new RecoverResult(fn($error) => defaultUserData()),
        new UnwrapResult()
    )
    ->toArray();
```

### File Processing

```php
use Cognesy\Stream\Transform\Result\Transducers\{ThenResult};use Cognesy\Stream\Transform\Result\Transducers\MapResult;use Cognesy\Stream\Transform\Result\Transducers\TapResult;

$processed = Transformation::define(
    new MapResult(fn($path) => Result::try(fn() => file_get_contents($path))),
    new TapResult(
        onSuccess: fn($content) => log("Read: " . strlen($content) . " bytes"),
        onFailure: fn($error) => log("Failed: $error")
    ),
    new ThenResult(fn($content) => parseYaml($content)),
    new ThenResult(fn($data) => transformData($data))
)->executeOn($filePaths);
```

### Batch Processing with Stats

```php
use Cognesy\Stream\Transform\Result\Decorators\ResultAwareReducer;

$stats = Transformation::define()
    ->withInput($operations)
    ->withSink(new ResultAwareReducer(
        onSuccess: fn($acc, $val) => [
            'count' => $acc['count'] + 1,
            'sum' => $acc['sum'] + $val,
            'errors' => $acc['errors']
        ],
        onFailure: fn($acc, $err) => [
            'count' => $acc['count'],
            'sum' => $acc['sum'],
            'errors' => $acc['errors'] + 1
        ],
        init: fn() => ['count' => 0, 'sum' => 0, 'errors' => 0]
    ))
    ->execute();
// ['count' => 150, 'sum' => 45000, 'errors' => 3]
```

### Parallel Processing with Tee

```php
use Cognesy\Stream\Support\Tee;
use Cognesy\Stream\TransformationStream;

// Split stream for different error handling strategies
[$critical, $optional] = Tee::split($operations, 2);

// Critical: fail fast
$criticalResults = TransformationStream::from($critical)
    ->through(new FilterSuccess())
    ->withSink(new AllSuccessReducer())
    ->getCompleted();

// Optional: best effort with defaults
$optionalResults = TransformationStream::from($optional)
    ->through(
        new RecoverResult(fn($e) => defaultValue()),
        new UnwrapResult()
    )
    ->toArray();
```

## Integration with Existing Components

Result-aware components work seamlessly with all existing Stream transducers:

```php
use Cognesy\Stream\Transform\Filter\Transducers\Filter;use Cognesy\Stream\Transform\Group\Transducers\Chunk;use Cognesy\Stream\Transform\Limit\Transducers\TakeN;use Cognesy\Stream\Transform\Map\Transducers\{Map};use Cognesy\Stream\Transform\Result\Transducers\{MapResult};use Cognesy\Stream\Transform\Result\Transducers\UnwrapResult;

$pipeline = Transformation::define(
    new MapResult(fn($x) => Result::try(fn() => riskyOperation($x))),
    new FilterSuccess(),
    new UnwrapResult(),
    new Filter(fn($x) => $x > 0),          // Regular Filter
    new Map(fn($x) => $x * 2),             // Regular Map
    new TakeN(10),                         // Regular TakeN
    new Chunk(5)                           // Regular Chunk
);
```

## Performance Characteristics

- **Single-pass processing** - All operations compose in one iteration
- **Early termination** - FirstSuccessReducer, AllSuccessReducer stop on condition
- **Zero-cost abstraction** - Minimal overhead for Result wrapping
- **Memory efficient** - No intermediate collections, just Result wrappers
- **Lazy evaluation** - Works with TransformationStream for large datasets

## Testing

Each component includes comprehensive documentation and usage examples. Test with:

```php
use Cognesy\Stream\Transformation;
use Cognesy\Utils\Result\Result;

test('MapResult transforms success values', function() {
    $result = Transformation::define(new MapResult(fn($x) => $x * 2))
        ->executeOn([Result::success(5), Result::failure('error')]);

    expect($result[0]->unwrap())->toBe(10);
    expect($result[1]->isFailure())->toBeTrue();
});
```

## See Also

- [Stream Package INTERNALS.md](../../INTERNALS.md) - Architecture overview
- [Stream Package CHEATSHEET.md](../../CHEATSHEET.md) - Quick reference
- [Utils Result RESULT.md](../../../utils/RESULT.md) - Result monad documentation
- [RESULT_TRANSDUCERS.md](../../../../tmp/RESULT_TRANSDUCERS.md) - Detailed proposal

---

**Result-aware components enable building robust, fault-tolerant streaming pipelines where errors are first-class citizens, handled explicitly through composition rather than exception-based control flow.**
