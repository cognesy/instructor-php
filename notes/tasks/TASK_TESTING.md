# Pragmatic Pest Testing Task Assignment

## Objective
Create a comprehensive test suite using Pest that provides robust coverage while maintaining simplicity, readability, and long-term maintainability. Focus on practical testing strategies that catch real bugs without over-engineering.

## Pre-Testing Requirements Gathering

### Testing Subject Specification
Before beginning test creation, gather the following information from the user:

#### 1. **Testing Scope Definition**
- [ ] **What is the primary subject to be tested?**
  - [ ] Single class or component
  - [ ] Feature or capability
  - [ ] API endpoint or service
  - [ ] Domain model or aggregate
  - [ ] Integration between components

#### 2. **Subject Details**
- [ ] **Exact location and identifier of code to test**
  - [ ] Full class name and namespace
  - [ ] Method signatures for unit testing
  - [ ] API endpoints for integration testing
  - [ ] Feature flows for end-to-end testing

#### 3. **Testing Context & Constraints**
- [ ] **What is the core business value this code provides?**
- [ ] **What are the critical failure scenarios to prevent?**
- [ ] **Are there performance requirements to validate?**
- [ ] **What external dependencies exist?**
- [ ] **Are there existing tests to extend or refactor?**

#### 4. **Coverage Expectations**
- [ ] **What level of testing is appropriate?**
  - [ ] Unit tests for core logic
  - [ ] Integration tests for boundaries
  - [ ] Feature tests for user scenarios
  - [ ] Performance tests for critical paths

#### 5. **Quality Standards**
- [ ] **What constitutes sufficient coverage?**
- [ ] **Are there specific edge cases to cover?**
- [ ] **Should tests serve as documentation?**
- [ ] **What is the expected maintenance overhead?**

---

## Phase 1: Test Strategy & Planning

### 1.1 Test Architecture Design
- [ ] **Identify testing boundaries**
  - [ ] Define unit vs integration test scope
  - [ ] Establish test data boundaries
  - [ ] Plan mock/stub strategies
  - [ ] Design test environment isolation

### 1.2 Test Categories Planning
- [ ] **Core functionality tests**
  - [ ] Happy path scenarios
  - [ ] Business logic validation
  - [ ] Domain invariant enforcement
  - [ ] State transition validation

- [ ] **Error handling tests**
  - [ ] Invalid input scenarios
  - [ ] Boundary condition failures
  - [ ] External dependency failures
  - [ ] Resource exhaustion scenarios

- [ ] **Integration tests**
  - [ ] Database interactions
  - [ ] External API calls
  - [ ] File system operations
  - [ ] Cross-component communication

### 1.3 Test Data Strategy
- [ ] **Fixture design**
  - [ ] Create realistic test data
  - [ ] Design data builders/factories
  - [ ] Plan test data lifecycle
  - [ ] Establish data isolation patterns

---

## Phase 2: Pest Test Structure & Organization

### 2.1 Test File Organization
- [ ] **Follow conventional structure**
  - [ ] Mirror source code directory structure
  - [ ] Use descriptive test file names
  - [ ] Group related tests logically
  - [ ] Separate unit from integration tests

### 2.2 Test Naming & Documentation
- [ ] **Use descriptive test names**
  - [ ] Describe the scenario being tested
  - [ ] Include expected outcome
  - [ ] Use domain language, not technical jargon
  - [ ] Make test intent immediately clear

```php
// Good: Descriptive and clear
it('calculates discount correctly for premium customers with bulk orders')

// Avoid: Technical but unclear
it('tests calculateDiscount method with type premium and quantity over 10')
```

### 2.3 Test Structure Patterns
- [ ] **Follow Arrange-Act-Assert pattern**
  - [ ] Arrange: Set up test data and conditions
  - [ ] Act: Execute the behavior being tested
  - [ ] Assert: Verify expected outcomes
  - [ ] Keep each section focused and minimal

```php
it('processes payment successfully for valid card', function () {
    // Arrange
    $payment = PaymentData::valid();
    $processor = new PaymentProcessor();
    
    // Act
    $result = $processor->process($payment);
    
    // Assert
    expect($result->isSuccess())->toBeTrue();
    expect($result->transactionId())->not->toBeEmpty();
});
```

---

## Phase 3: Core Testing Patterns

### 3.1 Value Object Testing
- [ ] **Test immutability and validation**
  - [ ] Verify construction with valid data
  - [ ] Test validation rules enforcement
  - [ ] Confirm immutable behavior
  - [ ] Validate equality comparisons

```php
describe('Email Value Object', function () {
    it('creates valid email from string', function () {
        $email = Email::from('user@example.com');
        expect($email->toString())->toBe('user@example.com');
    });
    
    it('rejects invalid email format', function () {
        $result = Email::tryFrom('invalid-email');
        expect($result->isFailure())->toBeTrue();
    });
});
```

### 3.2 Domain Model Testing
- [ ] **Test business logic and invariants**
  - [ ] Verify business rules enforcement
  - [ ] Test state transitions
  - [ ] Validate aggregate boundaries
  - [ ] Confirm domain events

```php
describe('Order Aggregate', function () {
    it('cannot add items after order is shipped', function () {
        $order = Order::create($customerId);
        $order->ship();
        
        $result = $order->addItem($item);
        
        expect($result->isFailure())->toBeTrue();
        expect($result->error())->toBeInstanceOf(OrderAlreadyShippedException::class);
    });
});
```

### 3.3 Service Testing
- [ ] **Test orchestration and boundaries**
  - [ ] Mock external dependencies appropriately
  - [ ] Test error propagation
  - [ ] Verify transaction boundaries
  - [ ] Validate side effects

### 3.4 Repository/Persistence Testing
- [ ] **Test data persistence patterns**
  - [ ] Use in-memory implementations for unit tests
  - [ ] Test database constraints in integration tests
  - [ ] Verify query correctness
  - [ ] Test data mapping accuracy

---

## Phase 4: Error Handling & Edge Cases

### 4.1 Result/Option Pattern Testing
- [ ] **Test monadic error handling**
  - [ ] Verify success path behavior
  - [ ] Test failure path behavior
  - [ ] Validate error message quality
  - [ ] Test error type specificity

```php
it('handles invalid input gracefully', function () {
    $result = UserService::createUser($invalidData);
    
    expect($result->isFailure())->toBeTrue();
    expect($result->error())->toBeInstanceOf(ValidationException::class);
    expect($result->error()->getMessage())->toContain('email is required');
});
```

### 4.2 Boundary Testing
- [ ] **Test edge conditions**
  - [ ] Empty collections
  - [ ] Maximum/minimum values
  - [ ] Null/optional parameters
  - [ ] Concurrent access scenarios

### 4.3 Exception Testing (When Unavoidable)
- [ ] **Test exceptional scenarios**
  - [ ] System-level failures
  - [ ] Unrecoverable errors
  - [ ] Programming errors
  - [ ] Resource exhaustion

---

## Phase 5: Integration & Feature Testing

### 5.1 Database Integration Tests
- [ ] **Test real database interactions**
  - [ ] Use test database with migrations
  - [ ] Test transaction rollback
  - [ ] Verify constraint enforcement
  - [ ] Test query performance

### 5.2 API Integration Tests
- [ ] **Test HTTP endpoints**
  - [ ] Test request/response mapping
  - [ ] Verify status codes
  - [ ] Test error responses
  - [ ] Validate content negotiation

### 5.3 Feature Tests
- [ ] **Test complete user scenarios**
  - [ ] Test end-to-end workflows
  - [ ] Verify business process completion
  - [ ] Test user experience flows
  - [ ] Validate cross-component integration

---

## Phase 6: Test Data & Fixtures

### 6.1 Test Data Builders
- [ ] **Create fluent data builders**
  - [ ] Default to valid data
  - [ ] Allow easy customization
  - [ ] Support chaining
  - [ ] Make intentions clear

```php
class UserDataBuilder 
{
    public static function valid(): self 
    {
        return new self([
            'email' => 'user@example.com',
            'name' => 'John Doe',
            'status' => UserStatus::Active,
        ]);
    }
    
    public function withEmail(string $email): self 
    {
        return $this->with('email', $email);
    }
    
    public function inactive(): self 
    {
        return $this->with('status', UserStatus::Inactive);
    }
}
```

### 6.2 Factory Patterns
- [ ] **Use factories for complex objects**
  - [ ] Create realistic test data
  - [ ] Support relationships
  - [ ] Allow customization
  - [ ] Maintain data consistency

### 6.3 Fixture Management
- [ ] **Manage test data lifecycle**
  - [ ] Clean up after tests
  - [ ] Isolate test data
  - [ ] Use database transactions
  - [ ] Reset state between tests

---

## Phase 7: Performance & Resource Testing

### 7.1 Performance Testing
- [ ] **Test critical performance paths**
  - [ ] Measure execution time for key operations
  - [ ] Test with realistic data volumes
  - [ ] Identify performance regressions
  - [ ] Validate scalability assumptions

### 7.2 Memory Testing
- [ ] **Prevent memory leaks**
  - [ ] Test large data processing
  - [ ] Verify resource cleanup
  - [ ] Test streaming operations
  - [ ] Monitor memory usage patterns

### 7.3 Concurrency Testing
- [ ] **Test concurrent scenarios**
  - [ ] Test race conditions
  - [ ] Verify locking mechanisms
  - [ ] Test deadlock prevention
  - [ ] Validate data consistency

---

## Phase 8: Test Maintenance & Quality

### 8.1 Test Readability
- [ ] **Write self-documenting tests**
  - [ ] Use clear, expressive assertions
  - [ ] Avoid complex setup
  - [ ] Keep tests focused
  - [ ] Use domain language

### 8.2 Test Reliability
- [ ] **Ensure consistent test results**
  - [ ] Eliminate flaky tests
  - [ ] Remove time-dependent tests
  - [ ] Control external dependencies
  - [ ] Use deterministic test data

### 8.3 Test Performance
- [ ] **Keep tests fast**
  - [ ] Minimize database operations
  - [ ] Use mocks appropriately
  - [ ] Parallel test execution
  - [ ] Efficient test data setup

---

## Phase 9: Pest-Specific Best Practices

### 9.1 Pest Features Usage
- [ ] **Leverage Pest's strengths**
  - [ ] Use descriptive test syntax
  - [ ] Utilize custom expectations
  - [ ] Group related tests with describe()
  - [ ] Use beforeEach/afterEach appropriately

### 9.2 Custom Expectations
- [ ] **Create domain-specific expectations**
```php
expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
});

// Usage
expect($email)->toBeValidEmail();
```

### 9.3 Shared Test Logic
- [ ] **Extract common test patterns**
  - [ ] Create reusable test traits
  - [ ] Use shared test cases
  - [ ] Build custom matchers
  - [ ] Create test utilities

---

## Testing Anti-Patterns to Avoid

### 9.1 Over-Testing
- [ ] **Avoid these common mistakes**
  - [ ] Testing framework code
  - [ ] Testing trivial getters/setters
  - [ ] Testing implementation details
  - [ ] Duplicating tests at multiple levels

### 9.2 Under-Testing
- [ ] **Don't neglect these areas**
  - [ ] Error handling paths
  - [ ] Edge cases and boundaries
  - [ ] Integration points
  - [ ] Business rule validation

### 9.3 Maintenance Nightmares
- [ ] **Avoid these patterns**
  - [ ] Overly complex test setup
  - [ ] Brittle mocking strategies
  - [ ] Unclear test intentions
  - [ ] Shared mutable test state

---

## Deliverables

### Primary Test Suite
- [ ] **Complete test coverage** for specified scope
- [ ] **Organized test structure** following conventions
- [ ] **Readable test documentation** through descriptive names
- [ ] **Maintainable test code** with clear patterns

### Supporting Materials
- [ ] **Test data builders** and factories
- [ ] **Custom expectations** for domain concepts
- [ ] **Test utilities** and helpers
- [ ] **Documentation** for test patterns used

### Quality Metrics
- [ ] **Coverage report** showing tested vs untested code
- [ ] **Performance benchmarks** for critical paths
- [ ] **Test execution time** analysis
- [ ] **Maintenance guidelines** for future developers

---

## Success Criteria

### Coverage Goals
- [ ] **Business logic**: 100% of critical business rules tested
- [ ] **Error handling**: All failure scenarios covered
- [ ] **Integration points**: Key boundaries verified
- [ ] **Edge cases**: Important boundary conditions tested

### Quality Standards
- [ ] **Tests are readable** and self-documenting
- [ ] **Tests are reliable** and consistent
- [ ] **Tests are fast** and efficient
- [ ] **Tests are maintainable** with clear patterns

### Long-term Sustainability
- [ ] **New team members** can understand and extend tests
- [ ] **Refactoring** doesn't break test suite unnecessarily  
- [ ] **Test failures** provide actionable information
- [ ] **Test maintenance** effort remains reasonable

---

## Pragmatic Testing Guidelines

### Focus on Value
- **Test behavior, not implementation** - Focus on what the code does, not how
- **Test what can break** - Prioritize testing paths that cause real problems
- **Test at the right level** - Unit tests for logic, integration tests for boundaries
- **Test the contract** - Focus on public APIs and expected behaviors

### Keep It Simple
- **One assertion per concept** - Test one thing at a time clearly
- **Minimal setup** - Reduce test complexity through simple arrangements
- **Clear intentions** - Make test purpose immediately obvious
- **Avoid clever code** - Tests should be boring and predictable

### Maintain Quality
- **Refactor tests** like production code
- **Delete obsolete tests** when requirements change
- **Update tests first** when changing behavior
- **Monitor test health** and fix flaky tests immediately

This framework ensures your Pest tests provide robust protection against regressions while remaining maintainable and valuable over the long term.