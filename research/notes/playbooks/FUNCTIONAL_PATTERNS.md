# Functional Programming in PHP: A Practical Playbook

*Subtitle: Transform your PHP codebase with functional patterns for cleaner, more reliable code*

---

## Introduction: Why Functional Programming Matters

The team has already begun adopting functional patterns with `Result`, `Pipeline`, and `StepByStep` classes. This playbook expands on that foundation to help you **think functionally** and write code that is:

- **Predictable**: Immutable data, pure functions, explicit error handling
- **Composable**: Small, reusable building blocks that combine naturally
- **Testable**: Isolated functions with clear inputs and outputs
- **Robust**: Monadic error handling prevents cascading failures

---

## Core Principle: Think in Terms of Data Flow, Not Data Structures

**Before (OOP thinking)**
```php
final class OrderProcessor {
    public function process(Order $order): void {
        $this->validate($order);
        $this->calculateTotals($order);
        $this->applyDiscounts($order);
        $this->sendConfirmation($order);
    }
}
```

**After (Functional thinking)**
```php
final class OrderProcessor {
    public function process(Order $order): Result {
        return Result::from($order)
            ->then($this->validate(...))
            ->then($this->calculateTotals(...))
            ->then($this->applyDiscounts(...))
            ->then($this->sendConfirmation(...));
    }
}
```

### Key Insight
Focus on **what transforms data** rather than **what contains data**. Each step is a pure transformation that can succeed or fail gracefully.

---

## Pattern 1: Embrace the Result Monad

*Subtitle: Make error handling explicit and composable*

### When to Use
- Any operation that can fail
- Chaining multiple fallible operations
- Avoiding deeply nested try/catch blocks
- Making error states part of your type system

### Current Implementation Analysis
The codebase already has a solid `Result<T, E>` implementation with:
- ✅ `map()` for value transformations
- ✅ `then()` for monadic chaining
- ✅ `recover()` for error handling
- ✅ `try()` for exception capture

### Advanced Result Patterns

**Sequential Processing with Early Exit**
```php
final class UserRegistration {
    public function register(array $data): Result {
        return Result::from($data)
            ->then($this->validateEmail(...))
            ->then($this->checkEmailUnique(...))
            ->then($this->hashPassword(...))
            ->then($this->createUser(...))
            ->then($this->sendWelcomeEmail(...));
    }

    private function validateEmail(array $data): Result {
        return filter_var($data['email'], FILTER_VALIDATE_EMAIL)
            ? Result::success($data)
            : Result::failure('Invalid email format');
    }
}
```

**Parallel Processing with Result Combination**
```php
final class ProfileBuilder {
    public function buildProfile(int $userId): Result {
        return Result::combine([
            $this->fetchUserData($userId),
            $this->fetchPreferences($userId),
            $this->fetchPermissions($userId),
        ])->map(fn($results) => new UserProfile(...$results));
    }

    // Add to Result class:
    public static function combine(array $results): Result {
        $values = [];
        foreach ($results as $result) {
            if ($result->isFailure()) {
                return $result; // Return first failure
            }
            $values[] = $result->unwrap();
        }
        return Result::success($values);
    }
}
```

**Result with Validation Accumulation**
```php
final class FormValidator {
    public function validate(array $data): Result {
        $errors = [];

        if (empty($data['name'])) $errors[] = 'Name required';
        if (empty($data['email'])) $errors[] = 'Email required';
        if (strlen($data['password'] ?? '') < 8) $errors[] = 'Password too short';

        return empty($errors)
            ? Result::success($data)
            : Result::failure($errors);
    }
}
```

---

## Pattern 2: Compose with Pipelines and Operators

*Subtitle: Build complex operations from simple, reusable pieces*

### Current Pipeline Analysis
The existing `Pipeline` system demonstrates good functional composition:
- ✅ Immutable state transformations via `CanCarryState`
- ✅ Composable operators via `OperatorStack`
- ✅ Middleware pattern for cross-cutting concerns
- ✅ Error handling strategies

### Functional Pipeline Patterns

**Operator Composition for Reusability**
```php
// Create reusable processing operators
final class TextProcessing {
    public static function normalize(): CanProcessState {
        return Call::withValue(fn($text) => trim(strtolower($text)));
    }

    public static function validateNotEmpty(): CanProcessState {
        return Call::withValue(function($text) {
            return empty($text)
                ? Result::failure('Text cannot be empty')
                : Result::success($text);
        });
    }

    public static function removeStopWords(array $stopWords): CanProcessState {
        return Call::withValue(function($text) use ($stopWords) {
            $words = explode(' ', $text);
            $filtered = array_filter($words, fn($word) => !in_array($word, $stopWords));
            return implode(' ', $filtered);
        });
    }
}

// Compose into pipelines
final class DocumentProcessor {
    public function process(string $document): CanCarryState {
        return Pipeline::builder()
            ->steps(
                TextProcessing::normalize(),
                TextProcessing::validateNotEmpty(),
                TextProcessing::removeStopWords(['the', 'a', 'an']),
                Call::withValue($this->extractKeywords(...)),
                Call::withValue($this->generateSummary(...))
            )
            ->build()
            ->executeWith(ProcessingState::with($document))
            ->await();
    }
}
```

**Conditional Processing with Predicates**
```php
final class ConditionalOperators {
    public static function when(callable $predicate, CanProcessState $operator): CanProcessState {
        return Call::withState(function(CanCarryState $state) use ($predicate, $operator) {
            return $predicate($state->value())
                ? $operator->process($state)
                : $state;
        });
    }

    public static function unless(callable $predicate, CanProcessState $operator): CanProcessState {
        return self::when(fn($value) => !$predicate($value), $operator);
    }
}

// Usage
Pipeline::builder()
    ->steps(
        ConditionalOperators::when(
            fn($user) => $user->isVip(),
            ApplyVipDiscount::of(20)
        ),
        ConditionalOperators::unless(
            fn($order) => $order->hasShipping(),
            AddShippingCost::of(9.99)
        )
    )
```

---

## Pattern 3: Prefer Function Composition Over Class Inheritance

*Subtitle: Build behavior through function combination*

### The Composition Mindset

**Instead of class hierarchies:**
```php
abstract class BaseEmailSender { /* ... */ }
class SmtpEmailSender extends BaseEmailSender { /* ... */ }
class SesEmailSender extends BaseEmailSender { /* ... */ }
```

**Think in terms of function composition:**
```php
final class EmailSender {
    public function __construct(
        private Transport $transport,
        private Formatter $formatter,
        private Validator $validator
    ) {}

    public function send(Email $email): Result {
        return Result::from($email)
            ->then($this->validator->validate(...))
            ->then($this->formatter->format(...))
            ->then($this->transport->send(...));
    }
}

// Transport is a simple function interface
interface Transport {
    public function send(FormattedEmail $email): Result;
}
```

### Higher-Order Functions for Reusability

**Retry Pattern as Higher-Order Function**
```php
final class Retry {
    public static function withBackoff(int $maxAttempts, int $baseDelay = 100): callable {
        return function(callable $operation) use ($maxAttempts, $baseDelay) {
            return function(...$args) use ($operation, $maxAttempts, $baseDelay) {
                $lastException = null;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    try {
                        return $operation(...$args);
                    } catch (Exception $e) {
                        $lastException = $e;
                        if ($attempt < $maxAttempts) {
                            usleep($baseDelay * (2 ** ($attempt - 1)) * 1000);
                        }
                    }
                }

                return Result::failure($lastException);
            };
        };
    }
}

// Usage
final class ApiClient {
    private callable $httpCall;

    public function __construct() {
        $this->httpCall = Retry::withBackoff(3, 200)(
            function($url, $data) {
                return $this->makeHttpRequest($url, $data);
            }
        );
    }

    public function call(string $url, array $data): Result {
        return ($this->httpCall)($url, $data);
    }
}
```

**Memoization for Expensive Operations**
```php
final class Memoize {
    public static function function(callable $fn): callable {
        $cache = [];

        return function(...$args) use ($fn, &$cache) {
            $key = serialize($args);

            if (!isset($cache[$key])) {
                $cache[$key] = $fn(...$args);
            }

            return $cache[$key];
        };
    }
}

// Usage
final class ExpensiveCalculator {
    private callable $calculate;

    public function __construct() {
        $this->calculate = Memoize::function($this->doExpensiveCalculation(...));
    }

    public function calculate(array $data): float {
        return ($this->calculate)($data);
    }
}
```

---

## Pattern 4: Currying and Partial Application

*Subtitle: Create specialized functions from general ones*

### Understanding Currying

Currying transforms a function that takes multiple arguments into a sequence of functions that each take a single argument.

**Manual Currying Pattern**
```php
final class CurriedOperations {
    // Instead of: validate($rules, $data)
    public static function validate(array $rules): callable {
        return function(array $data) use ($rules): Result {
            foreach ($rules as $field => $rule) {
                if (!$rule($data[$field] ?? null)) {
                    return Result::failure("Validation failed for {$field}");
                }
            }
            return Result::success($data);
        };
    }

    // Instead of: filter($predicate, $array)
    public static function filter(callable $predicate): callable {
        return function(array $items) use ($predicate): array {
            return array_filter($items, $predicate);
        };
    }

    // Instead of: map($transformer, $array)
    public static function map(callable $transformer): callable {
        return function(array $items) use ($transformer): array {
            return array_map($transformer, $items);
        };
    }
}
```

**Pipeline with Curried Functions**
```php
final class DataProcessor {
    public function processUsers(array $users): Result {
        $validateUser = CurriedOperations::validate([
            'email' => fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL),
            'age' => fn($age) => is_int($age) && $age >= 18,
        ]);

        $filterActive = CurriedOperations::filter(fn($user) => $user['active']);
        $mapToDto = CurriedOperations::map(fn($user) => UserDto::from($user));

        return Result::from($users)
            ->then($filterActive)
            ->then($mapToDto)
            ->then(fn($users) => array_map($validateUser, $users))
            ->then(fn($results) => $this->combineResults($results));
    }
}
```

### Practical Currying for Pipeline Configuration

**Database Query Builder with Currying**
```php
final class QueryBuilder {
    public static function select(array $fields): callable {
        return fn(string $table) => fn(array $conditions = []) =>
            new Query($fields, $table, $conditions);
    }

    public static function where(string $field, string $operator): callable {
        return fn($value) => fn(Query $query) =>
            $query->addCondition($field, $operator, $value);
    }
}

// Usage creates specialized, reusable query builders
$userFields = QueryBuilder::select(['id', 'name', 'email']);
$fromUsers = $userFields('users');
$activeUsers = $fromUsers(['active' => true]);

$byEmail = QueryBuilder::where('email', '=');
$withSpecificEmail = $byEmail('john@example.com');

// Compose into pipeline
$query = $activeUsers->pipe($withSpecificEmail);
```

**Configuration-Driven Processing**
```php
final class ConfigurableProcessor {
    public static function withConfig(array $config): callable {
        return function(mixed $data) use ($config) {
            return Pipeline::builder()
                ->steps(...self::buildStepsFromConfig($config))
                ->build()
                ->executeWith(ProcessingState::with($data))
                ->await();
        };
    }

    private static function buildStepsFromConfig(array $config): array {
        return array_map(
            fn($step) => match($step['type']) {
                'validate' => CurriedOperations::validate($step['rules']),
                'transform' => Call::withValue($step['transformer']),
                'filter' => Call::withValue(CurriedOperations::filter($step['predicate'])),
                default => throw new InvalidArgumentException("Unknown step type: {$step['type']}")
            },
            $config
        );
    }
}

// Usage
$processOrders = ConfigurableProcessor::withConfig([
    ['type' => 'validate', 'rules' => ['amount' => 'positive']],
    ['type' => 'filter', 'predicate' => fn($order) => $order->isValid()],
    ['type' => 'transform', 'transformer' => fn($order) => $order->calculate()],
]);

$result = $processOrders($orderData);
```

---

## Pattern 5: Immutability and State Management

*Subtitle: Prevent bugs through immutable design*

### Immutable Value Objects with Functional Updates

**Before (Mutable)**
```php
final class ShoppingCart {
    public function __construct(public array $items = []) {}

    public function addItem(CartItem $item): void {
        $this->items[] = $item; // Mutation!
    }

    public function removeItem(string $id): void {
        $this->items = array_filter($this->items, fn($item) => $item->id !== $id);
    }
}
```

**After (Immutable with Functional Updates)**
```php
final readonly class ShoppingCart {
    public function __construct(private array $items = []) {}

    public function items(): array {
        return $this->items;
    }

    public function withItem(CartItem $item): self {
        return new self([...$this->items, $item]);
    }

    public function withoutItem(string $id): self {
        return new self(
            array_filter($this->items, fn($item) => $item->id !== $id)
        );
    }

    public function mapItems(callable $transformer): self {
        return new self(array_map($transformer, $this->items));
    }

    public function filterItems(callable $predicate): self {
        return new self(array_filter($this->items, $predicate));
    }
}
```

### Lens Pattern for Deep Updates

**Lens Implementation for Nested Immutable Updates**
```php
final class Lens {
    private function __construct(
        private callable $getter,
        private callable $setter
    ) {}

    public static function at(string $property): self {
        return new self(
            getter: fn($object) => $object->$property,
            setter: fn($object, $value) => $object->{'with' . ucfirst($property)}($value)
        );
    }

    public function get(object $object): mixed {
        return ($this->getter)($object);
    }

    public function set(object $object, mixed $value): object {
        return ($this->setter)($object, $value);
    }

    public function modify(object $object, callable $modifier): object {
        $currentValue = $this->get($object);
        $newValue = $modifier($currentValue);
        return $this->set($object, $newValue);
    }

    public function compose(self $other): self {
        return new self(
            getter: fn($object) => $other->get($this->get($object)),
            setter: fn($object, $value) => $this->set($object, $other->set($this->get($object), $value))
        );
    }
}

// Usage for deep nested updates
final readonly class User {
    public function __construct(
        private string $name,
        private Address $address
    ) {}

    public function name(): string { return $this->name; }
    public function address(): Address { return $this->address; }

    public function withName(string $name): self {
        return new self($name, $this->address);
    }

    public function withAddress(Address $address): self {
        return new self($this->name, $address);
    }
}

// Deep updates without mutation
$nameLens = Lens::at('name');
$addressLens = Lens::at('address');
$streetLens = Lens::at('street');
$addressStreetLens = $addressLens->compose($streetLens);

$updatedUser = $addressStreetLens->set($user, '123 New Street');
```

---

## Pattern 6: Monadic Error Handling Patterns

*Subtitle: Chain operations safely without nested error checking*

### Option/Maybe Pattern for Null Safety

**Maybe Implementation**
```php
abstract readonly class Maybe {
    public static function some(mixed $value): Some {
        return new Some($value);
    }

    public static function none(): None {
        return new None();
    }

    public static function fromNullable(?mixed $value): self {
        return $value === null ? self::none() : self::some($value);
    }

    abstract public function map(callable $f): self;
    abstract public function flatMap(callable $f): self;
    abstract public function filter(callable $predicate): self;
    abstract public function getOrElse(mixed $default): mixed;
    abstract public function isEmpty(): bool;
}

final readonly class Some extends Maybe {
    public function __construct(private mixed $value) {}

    public function map(callable $f): Maybe {
        return Maybe::some($f($this->value));
    }

    public function flatMap(callable $f): Maybe {
        return $f($this->value);
    }

    public function filter(callable $predicate): Maybe {
        return $predicate($this->value) ? $this : Maybe::none();
    }

    public function getOrElse(mixed $default): mixed {
        return $this->value;
    }

    public function isEmpty(): bool {
        return false;
    }
}

final readonly class None extends Maybe {
    public function map(callable $f): Maybe {
        return $this;
    }

    public function flatMap(callable $f): Maybe {
        return $this;
    }

    public function filter(callable $predicate): Maybe {
        return $this;
    }

    public function getOrElse(mixed $default): mixed {
        return $default;
    }

    public function isEmpty(): bool {
        return true;
    }
}
```

**Safe Navigation with Maybe**
```php
final class UserService {
    public function getUserDisplayName(int $userId): string {
        return Maybe::fromNullable($this->findUser($userId))
            ->map(fn($user) => $user->profile())
            ->filter(fn($profile) => $profile->isPublic())
            ->map(fn($profile) => $profile->displayName())
            ->filter(fn($name) => !empty($name))
            ->getOrElse('Anonymous User');
    }

    public function getUserEmail(int $userId): Maybe {
        return Maybe::fromNullable($this->findUser($userId))
            ->flatMap(fn($user) => Maybe::fromNullable($user->email()))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
    }
}
```

### Validation Monad for Accumulating Errors

**Validation Implementation**
```php
abstract readonly class Validation {
    public static function success(mixed $value): ValidationSuccess {
        return new ValidationSuccess($value);
    }

    public static function failure(array $errors): ValidationFailure {
        return new ValidationFailure($errors);
    }

    abstract public function map(callable $f): self;
    abstract public function apply(Validation $validationFunction): self;
    abstract public function isSuccess(): bool;
    abstract public function getErrors(): array;
    abstract public function getValue(): mixed;
}

final readonly class ValidationSuccess extends Validation {
    public function __construct(private mixed $value) {}

    public function map(callable $f): Validation {
        return Validation::success($f($this->value));
    }

    public function apply(Validation $validationFunction): Validation {
        return $validationFunction->isSuccess()
            ? $validationFunction->map(fn($f) => $f($this->value))
            : $validationFunction;
    }

    public function isSuccess(): bool { return true; }
    public function getErrors(): array { return []; }
    public function getValue(): mixed { return $this->value; }
}

final readonly class ValidationFailure extends Validation {
    public function __construct(private array $errors) {}

    public function map(callable $f): Validation {
        return $this;
    }

    public function apply(Validation $validationFunction): Validation {
        return $validationFunction->isSuccess()
            ? $this
            : Validation::failure([...$this->errors, ...$validationFunction->getErrors()]);
    }

    public function isSuccess(): bool { return false; }
    public function getErrors(): array { return $this->errors; }
    public function getValue(): mixed { throw new RuntimeException('No value in failure'); }
}

// Usage for form validation with error accumulation
final class UserFormValidator {
    public function validate(array $data): Validation {
        $validateName = fn($name) => empty($name)
            ? Validation::failure(['Name is required'])
            : Validation::success($name);

        $validateEmail = fn($email) => !filter_var($email, FILTER_VALIDATE_EMAIL)
            ? Validation::failure(['Invalid email'])
            : Validation::success($email);

        $validateAge = fn($age) => (!is_int($age) || $age < 18)
            ? Validation::failure(['Age must be 18 or older'])
            : Validation::success($age);

        // Applicative validation accumulates ALL errors
        return Validation::success(fn($name) => fn($email) => fn($age) => [
                'name' => $name,
                'email' => $email,
                'age' => $age
            ])
            ->apply($validateName($data['name'] ?? ''))
            ->apply($validateEmail($data['email'] ?? ''))
            ->apply($validateAge($data['age'] ?? 0));
    }
}
```

---

## Pattern 7: Functional State Machines

*Subtitle: Model complex state transitions functionally*

### State Machine with Immutable Transitions

**State Machine Implementation**
```php
interface State {
    public function handle(Event $event): StateTransition;
}

final readonly class StateTransition {
    public function __construct(
        private State $newState,
        private array $sideEffects = []
    ) {}

    public function state(): State { return $this->newState; }
    public function sideEffects(): array { return $this->sideEffects; }

    public function withSideEffect(callable $effect): self {
        return new self($this->newState, [...$this->sideEffects, $effect]);
    }
}

// Order state machine example
enum OrderEvent {
    case PaymentReceived;
    case InventoryConfirmed;
    case Shipped;
    case Cancelled;
}

final readonly class PendingOrder implements State {
    public function __construct(private array $orderData) {}

    public function handle(Event $event): StateTransition {
        return match($event) {
            OrderEvent::PaymentReceived => new StateTransition(
                new PaidOrder($this->orderData),
                [fn() => $this->sendPaymentConfirmation()]
            ),
            OrderEvent::Cancelled => new StateTransition(
                new CancelledOrder($this->orderData),
                [fn() => $this->refundPayment()]
            ),
            default => new StateTransition($this)
        };
    }
}

final readonly class StateMachine {
    public function __construct(private State $currentState) {}

    public function transition(Event $event): self {
        $transition = $this->currentState->handle($event);

        // Execute side effects
        foreach ($transition->sideEffects() as $effect) {
            $effect();
        }

        return new self($transition->state());
    }

    public function currentState(): State {
        return $this->currentState;
    }
}
```

---

## Pattern 8: Event Sourcing with Functional Projections

*Subtitle: Build read models through pure functions*

### Functional Event Processing

**Event Projection as Pure Functions**
```php
final readonly class EventProjection {
    public function __construct(
        private callable $initialState,
        private callable $eventReducer
    ) {}

    public function project(array $events): mixed {
        return array_reduce(
            $events,
            $this->eventReducer,
            ($this->initialState)()
        );
    }
}

// User projection example
final class UserProjections {
    public static function userSummary(): EventProjection {
        return new EventProjection(
            initialState: fn() => [
                'id' => null,
                'name' => null,
                'email' => null,
                'loginCount' => 0,
                'lastLogin' => null,
                'isActive' => false
            ],
            eventReducer: function(array $state, DomainEvent $event): array {
                return match(get_class($event)) {
                    UserCreated::class => [...$state,
                        'id' => $event->userId,
                        'name' => $event->name,
                        'email' => $event->email,
                        'isActive' => true
                    ],
                    UserLoggedIn::class => [...$state,
                        'loginCount' => $state['loginCount'] + 1,
                        'lastLogin' => $event->timestamp
                    ],
                    UserDeactivated::class => [...$state, 'isActive' => false],
                    default => $state
                };
            }
        );
    }
}

// Usage
$events = $eventStore->getEventsForUser($userId);
$userSummary = UserProjections::userSummary()->project($events);
```

---

## Anti-Patterns to Avoid

### ❌ Don't: Mixed Mutable/Immutable Patterns
```php
// Confusing mix of mutation and immutability
final class BadExample {
    private array $items = [];

    public function addItem(Item $item): self { // Returns self but mutates
        $this->items[] = $item;
        return $this;
    }

    public function withNewItem(Item $item): self { // Claims immutability but doesn't deliver
        $this->items[] = $item;
        return clone $this;
    }
}
```

### ❌ Don't: Fake Functional Interfaces
```php
// Looks functional but has hidden side effects
interface BadProcessor {
    public function process(Data $data): Data; // Pure-looking but may mutate $data or have side effects
}

class BadImplementation implements BadProcessor {
    public function process(Data $data): Data {
        $data->setValue('modified'); // Mutation!
        $this->logger->log('Processing'); // Side effect!
        return $data;
    }
}
```

### ❌ Don't: Overuse of Monads
```php
// Not everything needs to be monadic
$result = Maybe::some($user)
    ->map(fn($u) => $u->getName()) // Simple getter doesn't need Maybe wrapping
    ->getOrElse('Unknown');

// Better:
$name = $user?->getName() ?? 'Unknown';
```

---

## Migration Strategy: From OOP to Functional

### Step 1: Start with Result/Maybe for Error Handling
Replace try/catch blocks and null checks with Result/Maybe monads.

### Step 2: Make Value Objects Immutable
Convert mutable domain objects to readonly classes with `withX()` methods.

### Step 3: Extract Pure Functions
Identify methods with no side effects and extract them as pure functions.

### Step 4: Compose with Pipelines
Chain operations using the existing Pipeline infrastructure.

### Step 5: Add Higher-Order Functions
Create function factories and combinators for common patterns.

---

## Performance Considerations

### Immutability Performance
- ✅ Use `readonly` classes to prevent accidental mutation
- ✅ Consider object pooling for frequently created value objects
- ✅ Use copy-on-write for large collections
- ⚠️ Profile before optimizing - immutability overhead is often negligible

### Function Composition Performance
- ✅ Prefer composition over deep inheritance
- ✅ Memoize expensive pure functions
- ⚠️ Avoid excessive currying in hot paths

---

## Testing Functional Code

### Pure Functions are Easy to Test
```php
// Pure function - deterministic, no side effects
test('calculateDiscount should apply percentage correctly', function() {
    $discount = calculateDiscount(100.0, 0.15);
    expect($discount)->toBe(15.0);
});

// Higher-order function testing
test('retry should attempt operation specified number of times', function() {
    $attempts = 0;
    $operation = function() use (&$attempts) {
        $attempts++;
        if ($attempts < 3) throw new Exception('Fail');
        return 'Success';
    };

    $retriedOperation = Retry::withBackoff(3)($operation);
    $result = $retriedOperation();

    expect($result)->toBe('Success');
    expect($attempts)->toBe(3);
});
```

### Property-Based Testing for Functional Code
```php
test('map preserves list length', function() {
    $input = range(1, 100);
    $output = FunctionalArray::map($input, fn($x) => $x * 2);

    expect(count($output))->toBe(count($input));
});

test('flatMap followed by map equals map followed by flatMap', function() {
    // Test monad laws
    $value = 42;
    $f = fn($x) => Result::success($x * 2);
    $g = fn($x) => $x + 1;

    $left = Result::success($value)->flatMap($f)->map($g);
    $right = Result::success($value)->map($g)->flatMap(fn($x) => $f($x));

    expect($left->unwrap())->toBe($right->unwrap());
});
```

---

## Summary: The Functional PHP Mindset

1. **Think in transformations, not mutations**
2. **Make error states explicit with Result/Maybe**
3. **Compose small, pure functions into larger operations**
4. **Use immutable data structures with functional updates**
5. **Leverage higher-order functions for reusability**
6. **Apply monadic patterns for safe operation chaining**
7. **Test pure functions for predictable behavior**

The goal isn't to write Haskell in PHP, but to adopt functional principles that make your PHP code more reliable, testable, and maintainable. Start small, be consistent, and gradually introduce these patterns where they add value.