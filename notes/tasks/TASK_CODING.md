# PHP 8.2+ Implementation Task Assignment

## Objective
Implement a focused, elegant solution following Domain-Driven Design principles with minimal initial complexity. Create clean, self-documenting code with superior developer experience using modern PHP 8.2+ features, strict typing, immutable data structures, and railway-oriented programming patterns.

## Pre-Implementation Requirements Gathering

### Implementation Scope Definition
Before beginning implementation, gather the following information from the user:

#### 1. **Implementation Subject**
- [ ] **What specific solution needs to be implemented?**
  - [ ] Reference to completed solution design document
  - [ ] Core domain capability or feature
  - [ ] Integration component or service
  - [ ] Data processing pipeline
  - [ ] API endpoint or controller

#### 2. **Solution Design Reference**
- [ ] **Which solution design document should be followed?**
- [ ] **Which specific sections from TASK_SOLUTION_DESIGN.md apply?**
  - [ ] Phase 1: Domain Analysis & Modeling
  - [ ] Phase 2: Architecture Design  
  - [ ] Phase 3: Data Design & Type System
  - [ ] Phase 4: Error Handling & Railway-Oriented Programming
  - [ ] Phase 5: Processing Pipeline Design
  - [ ] Other specific phases

#### 3. **Minimal Viable Implementation**
- [ ] **What is the core functionality to implement first?**
- [ ] **Which abstractions are essential vs. can be added later?**
- [ ] **What are the key public APIs that need to be designed?**
- [ ] **Which external dependencies are required?**

#### 4. **Quality Standards**
- [ ] **Are there existing code patterns to follow in this codebase?**
- [ ] **What testing approach should accompany the implementation?**
- [ ] **Are there performance requirements to consider?**
- [ ] **What level of error handling is expected?**

---

## Phase 1: Foundation & Core Domain Implementation

### 1.1 Domain Model Implementation
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 1: Domain Analysis & Modeling

- [ ] **Value Objects First** (TASK_SENIOR_DEV_CODE_REVIEW.md - Immutable Design)
  - [ ] Use `readonly` properties exclusively
  - [ ] Implement factory methods returning `Result<T, E>`
  - [ ] No public constructors - use named factory methods
  - [ ] Self-validating with domain-specific errors

```php
readonly class Email
{
    private function __construct(private string $value) {}
    
    public static function from(string $value): Result<self, ValidationError>
    {
        return match (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            false => Result::failure(ValidationError::invalidEmail($value)),
            default => Result::success(new self($value))
        };
    }
    
    public function toString(): string 
    {
        return $this->value;
    }
}
```

- [ ] **Entities with Clear Identity** (TASK_SOLUTION_DESIGN.md - Section 1.2)
  - [ ] Identity as strongly-typed value object
  - [ ] Immutable properties where possible
  - [ ] Behavioral methods, not just data containers
  - [ ] Clear aggregate boundaries

- [ ] **Enums Over Constants** (TASK_SENIOR_DEV_CODE_REVIEW.md - Modern PHP Features)
  - [ ] Backed enums for external representation
  - [ ] Rich behavior through enum methods
  - [ ] Use in `match()` expressions for state transitions

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    
    public function canTransitionTo(self $newStatus): bool
    {
        return match ([$this, $newStatus]) {
            [self::Pending, self::Confirmed] => true,
            [self::Confirmed, self::Shipped] => true,
            default => false
        };
    }
}
```

### 1.2 Custom Collections Implementation
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Data Structures & Collections

- [ ] **Typed Collections Over Arrays**
  - [ ] Domain-specific collection classes
  - [ ] Immutable with fluent transformation methods
  - [ ] Implement `Countable`, `IteratorAggregate`
  - [ ] Enforce collection invariants in constructor

```php
readonly class ProductList implements Countable, IteratorAggregate
{
    /** @param Product[] $products */
    private function __construct(private array $products) 
    {
        if (empty($products)) {
            throw new DomainException('Product list cannot be empty');
        }
    }
    
    public static function from(Product ...$products): self
    {
        return new self($products);
    }
    
    public function add(Product $product): self
    {
        return new self([...$this->products, $product]);
    }
    
    public function filterByCategory(Category $category): self
    {
        $filtered = array_filter(
            $this->products, 
            fn(Product $p) => $p->category()->equals($category)
        );
        
        return empty($filtered) 
            ? throw new DomainException('No products found in category')
            : new self(array_values($filtered));
    }
}
```

### 1.3 Result Type Implementation
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 4: Error Handling & Railway-Oriented Programming

- [ ] **Result Monad for Error Handling**
  - [ ] Generic `Result<T, E>` type
  - [ ] Chainable `map()`, `flatMap()`, `recover()` methods
  - [ ] No exceptions for business logic failures
  - [ ] Typed error hierarchy

```php
/** @template T, E */
readonly class Result
{
    private function __construct(
        private mixed $value,
        private mixed $error,
        private bool $isSuccess
    ) {}
    
    /** @return Result<T, never> */
    public static function success(mixed $value): self
    {
        return new self($value, null, true);
    }
    
    /** @return Result<never, E> */
    public static function failure(mixed $error): self
    {
        return new self(null, $error, false);
    }
    
    /** @template U */
    public function map(callable $fn): Result
    {
        return $this->isSuccess 
            ? self::success($fn($this->value))
            : $this;
    }
    
    /** @template U */
    public function flatMap(callable $fn): Result
    {
        return $this->isSuccess 
            ? $fn($this->value)
            : $this;
    }
}
```

---

## Phase 2: Application Layer Implementation

### 2.1 Use Case Implementation
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 2.1: Clean Architecture Layers

- [ ] **Command/Query Separation**
  - [ ] Commands return `Result<void, Error>`
  - [ ] Queries return `Result<T, Error>`
  - [ ] Single responsibility per use case
  - [ ] No business logic in use case - delegate to domain

```php
readonly class CreateOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ProductRepository $products,
        private EventPublisher $events
    ) {}
    
    public function handle(CreateOrderCommand $command): Result<OrderId, OrderCreationError>
    {
        return $this->validateProducts($command->productIds)
            ->flatMap(fn($products) => $this->createOrder($command, $products))
            ->map(fn($order) => $this->persistOrder($order))
            ->map(fn($order) => $this->publishEvents($order));
    }
    
    private function validateProducts(array $productIds): Result<ProductList, OrderCreationError>
    {
        // Implementation using Product repository
    }
}
```

### 2.2 Repository Interface Design
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 6.1: Repository Design

- [ ] **Aggregate-Focused Repositories**
  - [ ] One repository per aggregate root
  - [ ] Methods return `Result<T, RepositoryError>`
  - [ ] Use specification pattern for complex queries
  - [ ] No ORM leakage in interface

```php
interface OrderRepository
{
    public function save(Order $order): Result<void, RepositoryError>;
    
    public function findById(OrderId $id): Result<Order, RepositoryError>;
    
    public function findByCustomer(CustomerId $customerId): Result<OrderCollection, RepositoryError>;
    
    public function nextIdentity(): OrderId;
}
```

---

## Phase 3: Infrastructure Implementation (Minimal)

### 3.1 In-Memory Repository Implementation
**Reference**: TASK_TESTING.md - Phase 6.3: Fixture Management

- [ ] **Test-Friendly Infrastructure**
  - [ ] In-memory implementation for rapid development
  - [ ] Easy to test and reason about
  - [ ] Can be replaced with persistent implementation later

```php
final class InMemoryOrderRepository implements OrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];
    
    public function save(Order $order): Result<void, RepositoryError>
    {
        $this->orders[$order->id()->toString()] = $order;
        return Result::success(null);
    }
    
    public function findById(OrderId $id): Result<Order, RepositoryError>
    {
        return isset($this->orders[$id->toString()])
            ? Result::success($this->orders[$id->toString()])
            : Result::failure(RepositoryError::notFound($id));
    }
}
```

---

## Phase 4: Error Handling Implementation

### 4.1 Domain Error Types
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 4.2: Domain Error Design

- [ ] **Typed Error Hierarchy**
  - [ ] Abstract base error class
  - [ ] Domain-specific error types
  - [ ] Context and debugging information
  - [ ] No string-based errors

```php
abstract readonly class DomainError
{
    public function __construct(
        private string $message,
        private array $context = []
    ) {}
    
    abstract public function code(): string;
    
    public function message(): string
    {
        return $this->message;
    }
    
    public function context(): array
    {
        return $this->context;
    }
}

readonly class ProductNotFoundError extends DomainError
{
    public static function withId(ProductId $id): self
    {
        return new self(
            "Product not found",
            ['productId' => $id->toString()]
        );
    }
    
    public function code(): string
    {
        return 'PRODUCT_NOT_FOUND';
    }
}
```

### 4.2 Option Type for Null Safety
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 4.3: Option/Maybe Type Design

- [ ] **Option<T> for Optional Values**
  - [ ] Replace nullable types
  - [ ] Chainable operations
  - [ ] Clear null handling semantics

```php
/** @template T */
readonly class Option
{
    private function __construct(
        private mixed $value,
        private bool $hasValue
    ) {}
    
    public static function some(mixed $value): self
    {
        return new self($value, true);
    }
    
    public static function none(): self
    {
        return new self(null, false);
    }
    
    public function map(callable $fn): self
    {
        return $this->hasValue 
            ? self::some($fn($this->value))
            : self::none();
    }
    
    public function getOrElse(mixed $default): mixed
    {
        return $this->hasValue ? $this->value : $default;
    }
}
```

---

## Phase 5: Public API Design

### 5.1 Fluent Interface Design
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Developer Experience

- [ ] **Superior Developer Experience**
  - [ ] Method chaining where appropriate
  - [ ] IDE-discoverable APIs
  - [ ] Hard to misuse correctly
  - [ ] Consistent patterns across classes

```php
class OrderBuilder
{
    private array $items = [];
    private ?CustomerId $customerId = null;
    
    public static function create(): self
    {
        return new self();
    }
    
    public function forCustomer(CustomerId $customerId): self
    {
        $new = clone $this;
        $new->customerId = $customerId;
        return $new;
    }
    
    public function addItem(ProductId $productId, Quantity $quantity): self
    {
        $new = clone $this;
        $new->items[] = OrderItem::create($productId, $quantity);
        return $new;
    }
    
    public function build(): Result<Order, OrderCreationError>
    {
        return match (true) {
            $this->customerId === null => Result::failure(OrderCreationError::missingCustomer()),
            empty($this->items) => Result::failure(OrderCreationError::noItems()),
            default => Result::success(Order::create($this->customerId, OrderItemList::from(...$this->items)))
        };
    }
}
```

---

## Phase 6: Control Flow Implementation

### 6.1 Match Expressions Over Conditionals
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Control Flow

- [ ] **Use match() for Complex Logic**
  - [ ] Replace `if/else` chains and `switch/case`
  - [ ] Exhaustive pattern matching
  - [ ] Clear, declarative logic flow

```php
public function calculateShipping(Order $order): Result<Money, ShippingError>
{
    return match (true) {
        $order->isEmpty() => Result::failure(ShippingError::emptyOrder()),
        $order->total()->isLessThan(Money::from(50)) => Result::success(Money::from(10)),
        $order->total()->isLessThan(Money::from(100)) => Result::success(Money::from(5)),
        default => Result::success(Money::zero())
    };
}

public function processOrderStatus(OrderStatus $current, OrderEvent $event): Result<OrderStatus, OrderError>
{
    return match ([$current, $event->type()]) {
        [OrderStatus::Pending, EventType::Payment] => Result::success(OrderStatus::Confirmed),
        [OrderStatus::Confirmed, EventType::Shipment] => Result::success(OrderStatus::Shipped),
        [OrderStatus::Shipped, EventType::Delivery] => Result::success(OrderStatus::Delivered),
        default => Result::failure(OrderError::invalidTransition($current, $event))
    };
}
```

### 6.2 Guard Clauses and Early Returns
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Control Flow

- [ ] **Reduce Nesting with Guards**
  - [ ] Early returns for validation
  - [ ] Positive conditionals
  - [ ] Maximum 2-3 levels of nesting

```php
public function processPayment(PaymentRequest $request): Result<Payment, PaymentError>
{
    if ($request->amount()->isNegativeOrZero()) {
        return Result::failure(PaymentError::invalidAmount($request->amount()));
    }
    
    if (!$this->isValidCard($request->card())) {
        return Result::failure(PaymentError::invalidCard($request->card()));
    }
    
    return $this->chargeCard($request->card(), $request->amount())
        ->map(fn($charge) => Payment::from($charge, $request->amount()));
}
```

---

## Phase 7: Testing Implementation

### 7.1 Domain Model Testing
**Reference**: TASK_TESTING.md - Phase 3.2: Domain Model Testing

- [ ] **Test Business Logic and Invariants**
  - [ ] Use Pest's descriptive syntax
  - [ ] Test domain rules, not implementation
  - [ ] Use test data builders
  - [ ] Focus on behavior over state

```php
describe('Order Creation', function () {
    it('creates order with valid customer and items', function () {
        $customer = CustomerId::generate();
        $items = OrderItemList::from(
            OrderItem::create(ProductId::generate(), Quantity::from(2))
        );
        
        $result = Order::create($customer, $items);
        
        expect($result->isSuccess())->toBeTrue();
        expect($result->unwrap()->customerId())->toEqual($customer);
    });
    
    it('fails when creating order without items', function () {
        $customer = CustomerId::generate();
        
        $result = Order::create($customer, OrderItemList::empty());
        
        expect($result->isFailure())->toBeTrue();
        expect($result->error())->toBeInstanceOf(OrderValidationError::class);
    });
});
```

### 7.2 Test Data Builders
**Reference**: TASK_TESTING.md - Phase 6.1: Test Data Builders

- [ ] **Fluent Test Data Creation**
  - [ ] Default to valid data
  - [ ] Easy customization
  - [ ] Chainable methods

```php
class OrderTestBuilder
{
    private CustomerId $customerId;
    private array $items = [];
    
    public static function valid(): self
    {
        $builder = new self();
        $builder->customerId = CustomerId::generate();
        $builder->items = [OrderItem::create(ProductId::generate(), Quantity::from(1))];
        return $builder;
    }
    
    public function forCustomer(CustomerId $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }
    
    public function withItem(ProductId $productId, int $quantity = 1): self
    {
        $this->items[] = OrderItem::create($productId, Quantity::from($quantity));
        return $this;
    }
    
    public function build(): Order
    {
        return Order::create($this->customerId, OrderItemList::from(...$this->items))->unwrap();
    }
}
```

---

## Implementation Quality Checklist

### Core Code Quality (Must Follow)
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Multiple Sections

- [ ] **Strict Typing Throughout**
  - [ ] All parameters have type declarations
  - [ ] All return types are declared
  - [ ] All properties are typed
  - [ ] Use union types appropriately, avoid `mixed`

- [ ] **Immutable by Default**
  - [ ] Use `readonly` properties
  - [ ] Methods return new instances instead of mutating
  - [ ] Collections are immutable with transformation methods

- [ ] **Self-Documenting Code**
  - [ ] Intention-revealing names
  - [ ] Domain vocabulary consistently used
  - [ ] No comments needed for what the code does
  - [ ] Single level of abstraction per method

- [ ] **Modern PHP 8.2+ Features**
  - [ ] Constructor property promotion for simple classes
  - [ ] Match expressions instead of switch/case
  - [ ] Backed enums for constants
  - [ ] First-class callables where appropriate

### Architecture Quality
**Reference**: TASK_SOLUTION_DESIGN.md - Multiple Phases

- [ ] **Clean Architecture Respected**
  - [ ] Dependencies point inward
  - [ ] Domain layer has no external dependencies
  - [ ] Application layer orchestrates domain
  - [ ] Infrastructure implements interfaces

- [ ] **Domain-Driven Design**
  - [ ] Ubiquitous language in code
  - [ ] Clear aggregate boundaries
  - [ ] Entities vs Value Objects properly distinguished
  - [ ] Domain services for operations that don't belong to entities

### Error Handling Quality
**Reference**: TASK_SOLUTION_DESIGN.md - Phase 4

- [ ] **Railway-Oriented Programming**
  - [ ] Result types for operations that can fail
  - [ ] Option types for optional values
  - [ ] Chainable operations with map/flatMap
  - [ ] Typed errors instead of exceptions for business logic

### Performance Considerations
**Reference**: TASK_SENIOR_DEV_CODE_REVIEW.md - Performance & Resource Management

- [ ] **Memory Efficiency**
  - [ ] Use generators for large datasets
  - [ ] Proper resource cleanup
  - [ ] Avoid circular references
  - [ ] Lazy evaluation where appropriate

---

## Minimal File Structure Template

Start with this minimal structure and expand as needed:

```
src/
├── Domain/
│   ├── ValueObjects/
│   │   ├── Email.php
│   │   ├── Money.php
│   │   └── Quantity.php
│   ├── Entities/
│   │   └── Order.php
│   ├── Collections/
│   │   └── OrderItemList.php
│   ├── Errors/
│   │   ├── DomainError.php
│   │   └── ValidationError.php
│   └── Repositories/
│       └── OrderRepository.php
├── Application/
│   ├── Commands/
│   │   └── CreateOrderCommand.php
│   └── Handlers/
│       └── CreateOrderHandler.php
├── Infrastructure/
│   └── Repositories/
│       └── InMemoryOrderRepository.php
└── Shared/
    ├── Result.php
    └── Option.php

tests/
├── Unit/
│   ├── Domain/
│   └── Application/
└── Builders/
    └── OrderTestBuilder.php
```

---

## Success Criteria

### Functional Success
- [ ] All public APIs work as designed in solution document
- [ ] Business rules are enforced through domain model
- [ ] Error cases are handled gracefully with typed errors
- [ ] Tests provide confidence in business logic

### Code Quality Success
- [ ] Code is self-documenting without extensive comments
- [ ] APIs are discoverable and hard to misuse
- [ ] Performance is appropriate for expected load
- [ ] Memory usage is efficient and predictable

### Architecture Success
- [ ] Clean architecture boundaries are respected
- [ ] Domain logic is isolated from infrastructure concerns
- [ ] Dependencies point in correct direction
- [ ] Code can be easily extended and modified

## Implementation Guidelines

### Start Small, Grow Incrementally
- Begin with core value objects and entities
- Add minimal application layer
- Use in-memory infrastructure initially
- Expand based on actual needs, not anticipated ones

### Focus on Public APIs
- Design APIs from the consumer perspective
- Make common operations easy and safe
- Provide clear error messages and context
- Ensure consistent patterns across the codebase

### Maintain Quality Throughout
- Write tests alongside implementation
- Refactor when complexity increases
- Keep methods small and focused
- Maintain clear separation of concerns

Remember: The goal is elegant, maintainable code that clearly expresses business intent while being practical to implement and extend.