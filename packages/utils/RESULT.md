# Result Class Cheatsheet

**Dense playbook for development teams - when, why, and how to use Result monads for bulletproof error handling.**

## 🎯 When to Use Result

**✅ USE Result when:**
- API calls that might fail
- File operations (read/write/parse)
- Database operations
- External service integrations
- Validation chains
- Multiple operations where any can fail
- You want to avoid try/catch hell
- Need composable error handling

**❌ DON'T USE Result for:**
- Simple validation with immediate response
- Internal domain logic that should never fail
- Performance-critical hot paths
- When exceptions are more appropriate

## 🚀 Quick Start

```php
use Cognesy\Utils\Result\Result;

// Replace this:
try {
    $data = fetchApiData();
    $parsed = parseData($data);
    return processData($parsed);
} catch (Exception $e) {
    return null; // Lost error context!
}

// With this:
return Result::try(fn() => fetchApiData())
    ->map(fn($data) => parseData($data))
    ->map(fn($parsed) => processData($parsed));
```

## 🏗️ Creation Patterns

```php
// Direct creation
$success = Result::success($value);
$failure = Result::failure($error);

// From existing values
$result = Result::from($unknownValue);    // Auto-wraps based on type
$result = Result::from($existingResult);  // Returns as-is

// Safe execution
$result = Result::try(fn() => riskyOperation());

// Multiple operations - all must succeed
$result = Result::tryAll([$input],
    fn($x) => operation1($x),
    fn($x) => operation2($x)
);

// Try until condition met
$result = Result::tryUntil(
    fn($value) => $value > 10,
    [],
    fn() => attempt1(),
    fn() => attempt2()
);
```

## 🔍 State Checking

```php
// Basic state
$result->isSuccess();     // true/false
$result->isFailure();     // true/false

// Specific success values
$result->isSuccessAndNull();   // Success with null
$result->isSuccessAndTrue();   // Success with true
$result->isSuccessAndFalse();  // Success with false

// Type checking (only on Success)
$result->isType('string');           // Check primitive type
$result->isInstanceOf(User::class);  // Check object type
$result->matches(fn($x) => $x > 0);  // Custom predicate

// Safe value extraction
$value = $result->valueOr('default');      // Value or fallback
$exception = $result->exceptionOr(null);   // Exception or fallback
```

## ⚡ Transformation Chains

### Success-Side Transformations

```php
// map() - Transform success values
$result = Result::success(5)
    ->map(fn($x) => $x * 2)           // 10
    ->map(fn($x) => "Value: $x");     // "Value: 10"

// then() - Chain operations that return Results
$result = Result::success($userId)
    ->then(fn($id) => fetchUser($id))        // Returns Result<User>
    ->then(fn($user) => validateUser($user)) // Returns Result<bool>
    ->then(fn($valid) => saveUser($user));   // Returns Result<void>

// ensure() - Add validation guards
$result = Result::success(-5)
    ->ensure(
        fn($x) => $x > 0,                    // Predicate
        fn($x) => "Value $x must be positive" // Error message
    );

// tap() - Side effects without changing value
$result = Result::success($data)
    ->tap(fn($data) => logger()->info('Processing', $data))
    ->map(fn($data) => process($data));
```

### Failure-Side Transformations

```php
// recover() - Convert failure to success
$result = Result::failure('Network error')
    ->recover(fn($error) => 'Fallback data');

// mapError() - Transform error
$result = Result::failure('user not found')
    ->mapError(fn($error) => new UserNotFoundException($error));
```

## 🎬 Side Effects & Hooks

```php
$result->ifSuccess(fn($value) => logger()->info('Success', [$value]))
       ->ifFailure(fn($exception) => logger()->error('Failed', [$exception->getMessage()]));

// Method chaining works on both Success and Failure
Result::success('data')
    ->ifSuccess(fn($data) => cache()->store($data))
    ->ifFailure(fn($e) => metrics()->increment('errors'));
```

## 🛠️ Common Patterns

### API Integration
```php
function fetchUserProfile(int $userId): Result {
    return Result::try(fn() => $this->httpClient->get("/users/$userId"))
        ->ensure(
            fn($response) => $response->getStatusCode() === 200,
            fn($response) => "API error: {$response->getStatusCode()}"
        )
        ->map(fn($response) => json_decode($response->getBody(), true))
        ->ensure(
            fn($data) => isset($data['id']),
            fn($data) => 'Invalid user data structure'
        )
        ->map(fn($data) => new UserProfile($data));
}
```

### Validation Chains
```php
function validateRegistration(array $data): Result {
    return Result::success($data)
        ->ensure(
            fn($data) => !empty($data['email']),
            fn($data) => 'Email is required'
        )
        ->ensure(
            fn($data) => filter_var($data['email'], FILTER_VALIDATE_EMAIL),
            fn($data) => 'Invalid email format'
        )
        ->ensure(
            fn($data) => strlen($data['password']) >= 8,
            fn($data) => 'Password must be at least 8 characters'
        )
        ->map(fn($data) => new RegistrationRequest($data));
}
```

### File Processing Pipeline
```php
function processConfigFile(string $path): Result {
    return Result::try(fn() => file_get_contents($path))
        ->ensure(
            fn($content) => !empty($content),
            fn($content) => "Empty file: $path"
        )
        ->map(fn($content) => json_decode($content, true))
        ->ensure(
            fn($data) => json_last_error() === JSON_ERROR_NONE,
            fn($data) => 'Invalid JSON: ' . json_last_error_msg()
        )
        ->map(fn($data) => new Config($data));
}
```

### Database Transaction Pattern
```php
function transferFunds(int $fromId, int $toId, float $amount): Result {
    return Result::try(fn() => $this->db->beginTransaction())
        ->then(fn() => $this->debitAccount($fromId, $amount))
        ->then(fn() => $this->creditAccount($toId, $amount))
        ->tap(fn() => $this->db->commit())
        ->recover(function($error) {
            $this->db->rollback();
            return Result::failure("Transfer failed: {$error->getMessage()}");
        });
}
```

## 🔧 Error Handling Strategies

### Graceful Degradation
```php
$result = Result::try(fn() => $this->primaryService->getData())
    ->recover(fn($error) => $this->fallbackService->getData())
    ->recover(fn($error) => $this->getCachedData())
    ->valueOr([]);  // Empty array as final fallback
```

### Error Aggregation
```php
$results = Result::tryAll($items,
    fn($item) => $this->validateItem($item),
    fn($item) => $this->processItem($item),
    fn($item) => $this->saveItem($item)
);

if ($results->isFailure()) {
    $compositeError = $results->error(); // CompositeException with all errors
    foreach ($compositeError->getErrors() as $error) {
        logger()->error($error->getMessage());
    }
}
```

### Retry with Conditions
```php
$result = Result::tryUntil(
    fn($response) => $response->isSuccessful(),
    [$request],
    fn($req) => $this->httpClient->send($req),
    fn($req) => sleep(1) && $this->httpClient->send($req),
    fn($req) => sleep(2) && $this->httpClient->send($req)
);
```

## 📊 Best Practices

### ✅ DO
- **Chain operations** - Use `map()` and `then()` for pipelines
- **Early validation** - Use `ensure()` to fail fast
- **Meaningful errors** - Provide context in error messages
- **Type safety** - Use generics in docblocks: `@return Result<User, string>`
- **Compose operations** - Build complex flows from simple parts
- **Use `valueOr()`** - Always provide sensible defaults

### ❌ DON'T
- **Nest Results** - Avoid `Result<Result<T>>`
- **Ignore failures** - Always handle the failure case
- **Use for simple cases** - Don't over-engineer trivial operations
- **Mix with exceptions** - Pick one error handling strategy
- **Mutate in transformations** - Keep transformations pure

## 🧪 Testing Patterns

```php
// Test success path
$result = processData($validInput);
expect($result->isSuccess())->toBeTrue();
expect($result->unwrap())->toBeInstanceOf(ProcessedData::class);

// Test failure path
$result = processData($invalidInput);
expect($result->isFailure())->toBeTrue();
expect($result->error())->toContain('validation failed');

// Test transformations
$result = Result::success(5)
    ->map(fn($x) => $x * 2);
expect($result->unwrap())->toBe(10);

// Test error propagation
$result = Result::failure('error')
    ->map(fn($x) => $x * 2);
expect($result->isFailure())->toBeTrue();
expect($result->error())->toBe('error');
```

## 🔄 Migration from Try/Catch

```php
// Before: Exception-based
function processOrder(Order $order) {
    try {
        $this->validateOrder($order);
        $payment = $this->processPayment($order);
        $shipment = $this->createShipment($order, $payment);
        return $shipment;
    } catch (ValidationException $e) {
        logger()->error('Validation failed', [$e->getMessage()]);
        throw $e;
    } catch (PaymentException $e) {
        logger()->error('Payment failed', [$e->getMessage()]);
        throw $e;
    } catch (Exception $e) {
        logger()->error('Unexpected error', [$e->getMessage()]);
        throw $e;
    }
}

// After: Result-based
function processOrder(Order $order): Result {
    return Result::try(fn() => $this->validateOrder($order))
        ->then(fn() => $this->processPayment($order))
        ->then(fn($payment) => $this->createShipment($order, $payment))
        ->tap(fn($shipment) => logger()->info('Order processed', [$shipment->getId()]))
        ->ifFailure(fn($error) => logger()->error('Order failed', [$error->getMessage()]));
}
```

## 🎯 Performance Tips

- **Lazy evaluation** - Results are only computed when needed
- **No overhead on success** - Minimal wrapper around values
- **Memory efficient** - No stack unwinding like exceptions
- **Composable** - Build complex operations from simple parts
- **Avoid deep nesting** - Keep transformation chains flat

## 🔗 Integration Examples

### With Repositories
```php
interface UserRepository {
    public function findById(int $id): Result; // Result<User, string>
    public function save(User $user): Result;  // Result<void, string>
}
```

### With Services
```php
class OrderService {
    public function createOrder(CreateOrderRequest $request): Result {
        return $this->validateRequest($request)
            ->then(fn($validated) => $this->calculatePricing($validated))
            ->then(fn($priced) => $this->reserveInventory($priced))
            ->then(fn($reserved) => $this->saveOrder($reserved));
    }
}
```

### With Controllers
```php
class ApiController {
    public function createUser(Request $request): JsonResponse {
        return $this->userService->createUser($request->all())
            ->map(fn($user) => ['id' => $user->getId(), 'email' => $user->getEmail()])
            ->ifSuccess(fn($data) => response()->json($data, 201))
            ->ifFailure(fn($error) => response()->json(['error' => $error->getMessage()], 400))
            ->valueOr(response()->json(['error' => 'Unknown error'], 500));
    }
}
```

---

**Remember: Result is about making errors explicit, composable, and impossible to ignore. Use it to build robust, predictable systems where failure is always handled gracefully.**