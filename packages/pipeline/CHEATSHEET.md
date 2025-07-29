# Pipeline Cheatsheet

## Quick Start

```php
use Cognesy\Pipeline\Pipeline;

// Simple processing chain
$result = Pipeline::for($data)
    ->through(fn($x) => transform($x))
    ->through(fn($x) => validate($x))
    ->process()
    ->value();
```

## Creation Patterns

### Factory Methods
```php
// From value
$pipeline = Pipeline::for(42);

// From callable 
$pipeline = Pipeline::from(fn() => fetchData());

// Empty pipeline
$pipeline = Pipeline::make();
```

## Processing Operations

### Core Processors
```php
->through(fn($x) => $x * 2)                    // Transform value
->through(fn($x) => $x, NullStrategy::Allow)   // Handle nulls explicitly
->when(fn($computation) => condition($computation), fn($x) => process($x))  // Conditional
->tap(fn($x) => logger()->info($x))            // Side effects (doesn't change value)
->then(fn($result) => format($result->unwrap())) // Final transformation
```

### Computation-Aware Processors
```php
// Access full computation with tags
->through(function(Computation $computation) {
    $value = $computation->result()->unwrap();
    $tags = $computation->all(TimestampTag::class);
    return $computation->withResult(Result::success($transformed));
})
```

## Execution Methods

### Result Extraction
```php
$pending = $pipeline->process($initialValue);

$pending->value()       // Get raw value (null on failure)
$pending->result()      // Get Result object  
$pending->computation() // Get full Computation with tags
$pending->success()     // Check if successful (boolean)
$pending->failure()     // Get exception if failed
```

### Stream Processing
```php
foreach ($pipeline->stream([1, 2, 3]) as $pending) {
    echo $pending->value();
}

// Or extract from result
foreach ($pending->stream() as $item) {
    echo $item;
}
```

## Middleware & Hooks

### Middleware (Modern Approach)
```php
class LoggingMiddleware implements PipelineMiddlewareInterface {
    public function handle(Computation $computation, callable $next): Computation {
        logger()->info('Before processing');
        $result = $next($computation);
        logger()->info('After processing');
        return $result;
    }
}

$pipeline->withMiddleware(new LoggingMiddleware());
$pipeline->prependMiddleware(new UrgentMiddleware()); // Executes first
```

### Hooks (Legacy-Compatible) 
```php
->beforeEach(fn($computation) => $computation->with(new TimestampTag()))
->afterEach(fn($computation) => logger()->info($computation->result()->unwrap()))
->onFailure(fn($computation) => handleError($computation))
->finishWhen(fn($computation) => $computation->result()->unwrap() > 100)
```

## Tag System

### Adding Tags
```php
->withTag(new TimestampTag(), new UserTag($userId))

// In processors
->through(function(Computation $computation) {
    return $computation
        ->with(new MetricsTag('duration', $time))
        ->withResult(Result::success($processedData));
})
```

### Querying Tags
```php
$computation = $pending->computation();

$computation->has(TimestampTag::class)           // Check existence
$computation->count(MetricsTag::class)           // Count by type
$computation->first(UserTag::class)              // Get first
$computation->last(TimestampTag::class)          // Get latest
$computation->all(MetricsTag::class)             // Get all of type
$computation->all()                                // Get all tags
```

### Tag Management

```php
$cleaned = $computation->without(DebugTag::class, TempTag::class);
$updated = $computation->withResult(Result::success($newValue));
```

## Error Handling

### Null Strategies
```php
use Cognesy\Pipeline\Enums\NullStrategy;

->through(fn($x) => null, NullStrategy::Allow)  // Allow nulls
->through(fn($x) => null, NullStrategy::Fail)   // Fail on null  
->through(fn($x) => null, NullStrategy::Skip)   // Skip processing
```

### Exception Handling
```php
->through(fn($x) => mayThrow($x))
->onFailure(function($computation) {
    logger()->error('Pipeline failed: ' . $computation->result()->error());
    return $computation; // Continue with failure
})

// Check results
if (!$pending->success()) {
    $error = $pending->failure(); // Get exception
}
```

## Advanced Patterns

### Lazy Evaluation & Caching
```php
$pending = $pipeline->process($data); // Not executed yet

// Multiple calls use cached result
$value1 = $pending->value();  // Executes pipeline
$value2 = $pending->value();  // Uses cache
```

### Transformation Chains
```php
$final = $pending
    ->map(fn($x) => $x * 2)                    // Transform value
    ->mapComputation(fn($computation) => $computation->with($tag)) // Transform computation
    ->then(fn($x) => format($x));               // Chain computation
```

### Conditional Processing
```php
$pipeline = Pipeline::for($user)
    ->when(
        fn($computation) => $computation->result()->unwrap()->isAdmin(),
        fn($user) => $user->withPermissions('admin')
    )
    ->when(
        fn($computation) => $computation->result()->unwrap()->needsVerification(),
        fn($user) => verifyUser($user)
    );
```

### Complex Middleware
```php
class CacheMiddleware implements PipelineMiddlewareInterface {
    public function handle(Computation $computation, callable $next): Computation {
        $key = $this->getCacheKey($computation);
        
        if ($cached = $this->cache->get($key)) {
            return $computation->withResult(Result::success($cached));
        }
        
        $nextComputation = $next($computation);
        
        if ($nextComputation->result()->isSuccess()) {
            $this->cache->set($key, $nextComputation->result()->unwrap());
        }
        
        return $nextComputation;
    }
}
```

## Common Use Cases

### Data Processing Pipeline
```php
$result = Pipeline::for($rawData)
    ->withTag(new TraceTag($traceId))
    ->beforeEach(fn($computation) => $computation->with(new TimestampTag()))
    ->through(fn($data) => validate($data))
    ->through(fn($data) => normalize($data))
    ->through(fn($data) => enrich($data))
    ->afterEach(fn($computation) => logMetrics($computation))
    ->then(fn($result) => $result->unwrap())
    ->process()
    ->value();
```

### API Request Pipeline
```php
$response = Pipeline::from(fn() => $request->getData())
    ->withMiddleware(new AuthMiddleware(), new RateLimitMiddleware())
    ->through(fn($data) => validateInput($data))
    ->when(
        fn($computation) => needsTransformation($computation->result()->unwrap()),
        fn($data) => transform($data)
    )
    ->through(fn($data) => processRequest($data))
    ->onFailure(fn($computation) => logError($computation))
    ->process()
    ->result();
```

### Event Processing
```php
foreach (Pipeline::for($events)->stream($events) as $pending) {
    $result = $pending
        ->through(fn($event) => validate($event))
        ->through(fn($event) => enrich($event))
        ->tap(fn($event) => publishToQueue($event))
        ->value();
}
```

### Error Recovery
```php
$result = Pipeline::for($data)
    ->through(fn($x) => riskyOperation($x))
    ->through(function(Computation $computation) {
        if ($computation->result()->isFailure()) {
            return $computation->withResult(Result::success($defaultValue));
        }
        return $computation;
    })
    ->process()
    ->value();
```

## Performance Tips

1. **Use lazy evaluation**: Don't call `value()` until needed
2. **Batch tag queries**: Get multiple tag types in one operation  
3. **Avoid excessive computation copying**: Minimize `with()` calls in hot paths
4. **Cache pipeline instances**: Reuse configured pipelines for similar data
5. **Use tap() for side effects**: Avoids unnecessary value transformations

## Debugging

```php
// Add debug tags
->withTag(new DebugTag('checkpoint-1'))

// Log computation state
->tap(function($x) use ($computation) {
    logger()->debug('Pipeline state', [
        'value' => $x,
        'tags' => count($computation->all()),
        'memory' => memory_get_usage()
    ]);
})

// Inspect full computation
$computation = $pending->computation();
var_dump($computation->result(), $computation->all());
```