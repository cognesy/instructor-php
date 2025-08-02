# Senior PHP 8.2+ Developer Code Review Checklist

> A comprehensive code review checklist for sophisticated PHP backends emphasizing Domain-Driven Design, functional programming patterns, strict typing, and railway-oriented programming.

## Architecture & Domain Design

### Domain-Driven Design (DDD)
- [ ] **Bounded Context Clarity**: Are domain boundaries clearly defined and respected?
- [ ] **Ubiquitous Language**: Do class/method names reflect the domain language consistently across contexts?
- [ ] **Aggregate Design**: Are aggregates properly sized with clear invariants and transactional boundaries?
- [ ] **Entity vs Value Object**: Are entities used for identity-based objects and value objects for descriptive attributes?
- [ ] **Domain Services**: Are domain operations that don't belong to entities/VOs properly encapsulated in domain services?
- [ ] **Repository Abstraction**: Do repositories abstract persistence concerns without leaking implementation details?
- [ ] **Anti-Corruption Layer**: Are external systems properly isolated through ACLs when needed?

### Clean Architecture
- [ ] **Dependency Direction**: Do dependencies point inward (domain ← application ← infrastructure)?
- [ ] **Interface Segregation**: Are interfaces focused and client-specific rather than fat?
- [ ] **Single Responsibility**: Does each class have a single, clear reason to change?
- [ ] **Open/Closed Principle**: Can behavior be extended without modifying existing code?
- [ ] **Composition over Inheritance**: Is composition preferred when extending behavior?

## Type System & PHP 8.2+ Features

### Strict Typing
- [ ] **Type Declarations**: Are all parameters, return types, and properties strictly typed?
- [ ] **Union Types**: Are union types used appropriately without becoming catch-all types?
- [ ] **Intersection Types**: Are intersection types used for proper contract composition?
- [ ] **Generics Documentation**: Are generic type annotations present in PHPDoc for collections/containers?
- [ ] **Nullable Types**: Are nullable types explicit (`?Type`) rather than implicit?
- [ ] **Mixed Type**: Is `mixed` type avoided unless absolutely necessary?

### Modern PHP Features
- [ ] **Readonly Properties**: Are immutable properties marked `readonly`?
- [ ] **Constructor Promotion**: Is constructor property promotion used for simple value objects?
- [ ] **Enums**: Are string/int constants replaced with backed enums where appropriate?
- [ ] **Attributes**: Are attributes used instead of annotations for metadata?
- [ ] **Match Expressions**: Are `match()` expressions used instead of `switch/case` or nested conditionals?
- [ ] **Named Arguments**: Are named arguments used for complex constructor calls?
- [ ] **First-Class Callables**: Are `fn()` and first-class callable syntax used appropriately?

## Functional Programming & Immutability

### Immutable Design
- [ ] **Immutable by Default**: Are objects immutable unless mutation is explicitly required?
- [ ] **Value Object Immutability**: Do value objects enforce immutability through design?
- [ ] **Collection Immutability**: Are custom collection classes immutable with fluent transformation methods?
- [ ] **Method Chaining**: Do transformation methods return new instances rather than mutating?
- [ ] **Defensive Copying**: Are mutable dependencies defensively copied when stored?

### Functional Patterns
- [ ] **Pure Functions**: Are functions free of side effects where possible?
- [ ] **Higher-Order Functions**: Are higher-order functions used for algorithm abstraction?
- [ ] **Function Composition**: Is complex logic built through function composition?
- [ ] **Lazy Evaluation**: Are expensive operations deferred until needed?
- [ ] **Monadic Patterns**: Are Option/Maybe and Result types used for null safety and error handling?

## Error Handling & Railway-Oriented Programming

### Result Type Pattern
- [ ] **No Exception-Based Flow Control**: Are exceptions reserved for truly exceptional conditions?
- [ ] **Result Monad**: Is `Result<T, E>` type used for operations that can fail?
- [ ] **Option/Maybe Type**: Is `Option<T>` used instead of nullable types for optional values?
- [ ] **Railway Composition**: Are operations chained using `map()`, `flatMap()`, `recover()` patterns?
- [ ] **Early Returns**: Are early returns used to avoid deeply nested conditionals?
- [ ] **Error Type Safety**: Are error conditions represented as typed values rather than strings?

### Error Representation
- [ ] **Domain Errors**: Are business rule violations represented as domain-specific error types?
- [ ] **Infrastructure Errors**: Are infrastructure failures clearly separated from domain errors?
- [ ] **Error Messages**: Are error messages helpful and actionable for developers?
- [ ] **Error Context**: Do errors include sufficient context for debugging without exposing internals?

## Data Structures & Collections

### Custom Collections
- [ ] **Typed Collections**: Are raw arrays replaced with typed collection classes?
- [ ] **Collection Invariants**: Do collections enforce their own invariants (non-empty, unique items, etc.)?
- [ ] **Fluent Interface**: Do collections provide fluent transformation methods?
- [ ] **Iterator Implementation**: Do collections implement appropriate iterator interfaces?
- [ ] **Collection Operations**: Are common operations (map, filter, reduce) available and properly typed?

### Data Encapsulation
- [ ] **No Public Arrays**: Are public array properties replaced with proper encapsulation?
- [ ] **Value Object Wrapping**: Are primitive obsessions eliminated through value objects?
- [ ] **Builder Pattern**: Are complex object constructions handled through builders?
- [ ] **Factory Methods**: Are named constructors used for different creation contexts?

## Code Quality & Readability

### Naming & Clarity
- [ ] **Intention-Revealing Names**: Do names clearly express intent without requiring comments?
- [ ] **Domain Vocabulary**: Do names use domain-specific terminology consistently?
- [ ] **Verb-Noun Clarity**: Do method names follow verb-noun patterns appropriately?
- [ ] **Boolean Method Names**: Do boolean methods use `is*`, `has*`, `can*` prefixes?
- [ ] **No Abbreviations**: Are abbreviations avoided in favor of full, descriptive names?

### Method Design
- [ ] **Single Level of Abstraction**: Do methods operate at a single level of abstraction?
- [ ] **Parameter Object**: Are multiple parameters grouped into parameter objects?
- [ ] **Command-Query Separation**: Are commands (void) separated from queries (return values)?
- [ ] **Method Length**: Are methods focused and concise (prefer < 20 lines)?
- [ ] **Cyclomatic Complexity**: Is complexity kept low through proper decomposition?

### Control Flow
- [ ] **Match over Switch**: Are `match()` expressions used instead of `switch/case`?
- [ ] **Guard Clauses**: Are guard clauses used to reduce nesting?
- [ ] **No Deep Nesting**: Is nesting limited to 2-3 levels maximum?
- [ ] **Positive Conditionals**: Are positive conditions preferred over negative ones?
- [ ] **Polymorphism over Conditionals**: Is polymorphism used instead of type checking?

## Performance & Resource Management

### Memory Management
- [ ] **Generator Usage**: Are generators used for large dataset processing?
- [ ] **Lazy Loading**: Are expensive resources loaded only when needed?
- [ ] **Resource Cleanup**: Are resources (files, connections) properly cleaned up?
- [ ] **Circular Reference Prevention**: Are circular references avoided in object graphs?
- [ ] **Streaming Processing**: Are large datasets processed in streams rather than loaded entirely?

### Algorithmic Efficiency
- [ ] **Appropriate Data Structures**: Are the most efficient data structures chosen for use cases?
- [ ] **Pagination**: Are lists paginated rather than loaded entirely?
- [ ] **Caching Strategy**: Are expensive computations cached appropriately?
- [ ] **Database N+1**: Are N+1 query problems avoided?
- [ ] **Batch Operations**: Are bulk operations used instead of loops where possible?

## Parsing & Data Processing

### Parser Design
- [ ] **Proper Parsers**: Are complex data formats parsed with proper lexer/parser combinations?
- [ ] **No Regex Validation**: Are regular expressions avoided for complex validation?
- [ ] **AST Construction**: Are abstract syntax trees built for complex data transformation?
- [ ] **Parser Combinators**: Are parser combinators used for composable parsing?
- [ ] **Error Recovery**: Do parsers provide meaningful error messages with location information?

### Data Transformation
- [ ] **Transformation Pipelines**: Are data transformations built as composable pipelines?
- [ ] **Input Validation**: Is input validated at system boundaries?
- [ ] **Output Serialization**: Is output properly serialized with type safety?
- [ ] **Schema Evolution**: Can data schemas evolve without breaking existing code?

## Testing & Verification

### Test Design
- [ ] **Domain Test Language**: Do tests use domain language rather than implementation details?
- [ ] **Test Data Builders**: Are test data builders used for complex object construction?
- [ ] **Property-Based Testing**: Are property-based tests used where appropriate?
- [ ] **Contract Testing**: Are interfaces tested through contract tests?
- [ ] **Integration Boundaries**: Are integration tests focused on boundaries rather than internals?

### Testability
- [ ] **Dependency Injection**: Are dependencies injected rather than hard-coded?
- [ ] **Test Doubles**: Are test doubles used appropriately (not overused)?
- [ ] **Deterministic Tests**: Are tests deterministic and independent?
- [ ] **Fast Feedback**: Do unit tests run quickly (< 1s for entire suite)?

## Documentation & Self-Documenting Code

### Code Documentation
- [ ] **Self-Documenting**: Is the code readable without extensive comments?
- [ ] **API Documentation**: Are public APIs documented with examples?
- [ ] **Design Decisions**: Are architectural decisions documented where they're not obvious?
- [ ] **Usage Examples**: Are complex APIs accompanied by usage examples?
- [ ] **PHPDoc Types**: Are complex types properly documented in PHPDoc?

### Comments (When Necessary)
- [ ] **Why, Not What**: Do comments explain why rather than what?
- [ ] **Business Rules**: Are complex business rules explained?
- [ ] **Workarounds**: Are temporary workarounds clearly marked?
- [ ] **Performance Optimizations**: Are non-obvious optimizations explained?

## Security & Reliability

### Input Handling
- [ ] **Input Validation**: Is all external input validated at boundaries?
- [ ] **SQL Injection**: Are parameterized queries used exclusively?
- [ ] **Type Coercion**: Is implicit type coercion avoided?
- [ ] **Boundary Validation**: Are numerical boundaries properly validated?

### Error Information
- [ ] **Information Disclosure**: Do error messages avoid exposing internal details?
- [ ] **Log Security**: Are sensitive values excluded from logs?
- [ ] **Debug Information**: Is debug information disabled in production?

## Developer Experience

### API Design
- [ ] **Fluent Interfaces**: Are APIs chainable where it makes sense?
- [ ] **Discoverable**: Are APIs discoverable through IDE autocompletion?
- [ ] **Hard to Misuse**: Is it difficult to use APIs incorrectly?
- [ ] **Consistent**: Are similar operations expressed similarly across the codebase?
- [ ] **Type-Safe Configuration**: Are configuration objects type-safe rather than array-based?

### Error Messages
- [ ] **Actionable Errors**: Do error messages suggest concrete actions?
- [ ] **Context Preservation**: Do errors include relevant context?
- [ ] **Developer-Friendly**: Are error messages written for developers, not end users?
- [ ] **Error Codes**: Are errors categorized with consistent error codes?

## Advanced Patterns

### Monadic Patterns
- [ ] **Chain of Operations**: Are failing operations properly chained?
- [ ] **Error Propagation**: Do errors propagate without explicit checking?
- [ ] **Success/Failure Paths**: Are happy and sad paths clearly separated?
- [ ] **Resource Safety**: Are resources safely managed in monadic chains?

### Event-Driven Architecture
- [ ] **Domain Events**: Are significant business events published?
- [ ] **Event Immutability**: Are events immutable value objects?
- [ ] **Event Versioning**: Can events evolve without breaking consumers?
- [ ] **Eventual Consistency**: Is eventual consistency properly handled?

## Specific Anti-Patterns to Avoid

### Code Smells
- [ ] **God Classes**: Are classes focused on single responsibilities?
- [ ] **Feature Envy**: Do classes primarily work with their own data?
- [ ] **Primitive Obsession**: Are primitives wrapped in domain objects?
- [ ] **Long Parameter Lists**: Are parameter objects used for complex operations?
- [ ] **Switch Statement Smell**: Is polymorphism used instead of type switching?

### Performance Anti-Patterns
- [ ] **Premature Optimization**: Are optimizations driven by actual profiling?
- [ ] **Memory Leaks**: Are large objects and arrays properly scoped?
- [ ] **Inefficient Loops**: Are loops optimized for the expected data size?
- [ ] **Unnecessary ProcessingStates**: Are expensive operations cached or avoided?

## Final Considerations

- [ ] **Backward Compatibility**: Are changes backward compatible or properly versioned?
- [ ] **Deployment Safety**: Can changes be deployed safely without downtime?
- [ ] **Monitoring**: Are critical paths properly instrumented?
- [ ] **Graceful Degradation**: Does the system handle partial failures gracefully?
- [ ] **Future Extensibility**: Can the design accommodate likely future changes?

---

## Review Mindset

When conducting reviews with this checklist, remember:

- **Start with Architecture**: Review high-level design before diving into implementation details
- **Question Complexity**: Challenge any complexity that doesn't directly serve business needs
- **Favor Explicitness**: Prefer explicit behavior over magical implicit behavior
- **Think Long-Term**: Consider maintenance burden over the next 2-3 years
- **Balance Pragmatism**: Apply patterns judiciously - not every class needs to be a perfect domain model
- **Developer Empathy**: Consider the experience of the next developer who will work with this code

The goal is not perfect adherence to every point, but thoughtful application of these principles to create maintainable, robust, and expressive code that serves the business domain effectively.