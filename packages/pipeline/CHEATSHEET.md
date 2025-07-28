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
->when(fn($env) => condition($env), fn($x) => process($x))  // Conditional
->tap(fn($x) => logger()->info($x))            // Side effects (doesn't change value)
->then(fn($result) => format($result->unwrap())) // Final transformation
```

### Envelope-Aware Processors
```php
// Access full envelope with stamps
->through(function(Envelope $env) {
    $value = $env->getResult()->unwrap();
    $stamps = $env->all(TimestampStamp::class);
    return $env->withMessage(Result::success($transformed));
})
```

## Execution Methods

### Result Extraction
```php
$pending = $pipeline->process($initialValue);

$pending->value()       // Get raw value (null on failure)
$pending->result()      // Get Result object  
$pending->envelope()    // Get full Envelope with stamps
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
    public function handle(Envelope $envelope, callable $next): Envelope {
        logger()->info('Before processing');
        $result = $next($envelope);
        logger()->info('After processing');
        return $result;
    }
}

$pipeline->withMiddleware(new LoggingMiddleware());
$pipeline->prependMiddleware(new UrgentMiddleware()); // Executes first
```

### Hooks (Legacy-Compatible) 
```php
->beforeEach(fn($env) => $env->with(new TimestampStamp()))
->afterEach(fn($env) => logger()->info($env->getResult()->unwrap()))
->onFailure(fn($env) => handleError($env))
->finishWhen(fn($env) => $env->getResult()->unwrap() > 100)
```

## Stamp System

### Adding Stamps
```php
->withStamp(new TimestampStamp(), new UserStamp($userId))

// In processors
->through(function(Envelope $env) {
    return $env
        ->with(new MetricsStamp('duration', $time))
        ->withMessage(Result::success($processedData));
})
```

### Querying Stamps
```php
$envelope = $pending->envelope();

$envelope->has(TimestampStamp::class)           // Check existence
$envelope->count(MetricsStamp::class)           // Count by type
$envelope->first(UserStamp::class)              // Get first
$envelope->last(TimestampStamp::class)          // Get latest
$envelope->all(MetricsStamp::class)             // Get all of type
$envelope->all()                                // Get all stamps
```

### Stamp Management
```php
$cleaned = $envelope->without(DebugStamp::class, TempStamp::class);
$updated = $envelope->withMessage(Result::success($newValue));
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
->onFailure(function($env) {
    logger()->error('Pipeline failed: ' . $env->getResult()->error());
    return $env; // Continue with failure
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
    ->mapEnvelope(fn($env) => $env->with($stamp)) // Transform envelope
    ->then(fn($x) => format($x));               // Chain computation
```

### Conditional Processing
```php
$pipeline = Pipeline::for($user)
    ->when(
        fn($env) => $env->getResult()->unwrap()->isAdmin(),
        fn($user) => $user->withPermissions('admin')
    )
    ->when(
        fn($env) => $env->getResult()->unwrap()->needsVerification(),
        fn($user) => verifyUser($user)
    );
```

### Complex Middleware
```php
class CacheMiddleware implements PipelineMiddlewareInterface {
    public function handle(Envelope $envelope, callable $next): Envelope {
        $key = $this->getCacheKey($envelope);
        
        if ($cached = $this->cache->get($key)) {
            return $envelope->withMessage(Result::success($cached));
        }
        
        $result = $next($envelope);
        
        if ($result->getResult()->isSuccess()) {
            $this->cache->set($key, $result->getResult()->unwrap());
        }
        
        return $result;
    }
}
```

## Common Use Cases

### Data Processing Pipeline
```php
$result = Pipeline::for($rawData)
    ->withStamp(new TraceStamp($traceId))
    ->beforeEach(fn($env) => $env->with(new TimestampStamp()))
    ->through(fn($data) => validate($data))
    ->through(fn($data) => normalize($data))
    ->through(fn($data) => enrich($data))
    ->afterEach(fn($env) => logMetrics($env))
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
        fn($env) => needsTransformation($env->getResult()->unwrap()),
        fn($data) => transform($data)
    )
    ->through(fn($data) => processRequest($data))
    ->onFailure(fn($env) => logError($env))
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
    ->through(function(Envelope $env) {
        if ($env->getResult()->isFailure()) {
            return $env->withMessage(Result::success($defaultValue));
        }
        return $env;
    })
    ->process()
    ->value();
```

## Performance Tips

1. **Use lazy evaluation**: Don't call `value()` until needed
2. **Batch stamp queries**: Get multiple stamp types in one operation  
3. **Avoid excessive envelope copying**: Minimize `with()` calls in hot paths
4. **Cache pipeline instances**: Reuse configured pipelines for similar data
5. **Use tap() for side effects**: Avoids unnecessary value transformations

## Debugging

```php
// Add debug stamps
->withStamp(new DebugStamp('checkpoint-1'))

// Log envelope state
->tap(function($x) use ($envelope) {
    logger()->debug('Pipeline state', [
        'value' => $x,
        'stamps' => count($envelope->all()),
        'memory' => memory_get_usage()
    ]);
})

// Inspect full envelope
$envelope = $pending->envelope();
var_dump($envelope->getResult(), $envelope->all());
```