# Code Review Task Assignment

## Objective
Perform comprehensive code review of a specified code element (package, class, interface, trait, or capability) to assess code quality, architecture, security, performance, and adherence to best practices.

## Pre-Review Requirements Gathering

### Review Subject Specification
Before beginning the review, the agent must gather the following information from the user:

#### 1. **Review Scope Definition**
- [ ] **What is the primary subject of this review?**
  - [ ] Single class/interface/trait/enum
  - [ ] Entire package/namespace
  - [ ] Specific capability/feature implementation
  - [ ] Module or component
  - [ ] Set of related files

#### 2. **Subject Details**
- [ ] **Exact path or identifier of the code to review**
  - [ ] Full file path(s) if specific files
  - [ ] Package/namespace if reviewing a package
  - [ ] Class name(s) and location if reviewing classes
  - [ ] Feature description if reviewing a capability

#### 3. **Review Context**
- [ ] **What is the primary purpose/responsibility of this code?**
- [ ] **Are there any specific concerns or areas of focus?**
  - [ ] Performance issues
  - [ ] Security vulnerabilities  
  - [ ] Design pattern implementation
  - [ ] Code maintainability
  - [ ] Testing coverage
  - [ ] Documentation quality

#### 4. **Review Constraints**
- [ ] **Are there any areas that should be excluded from review?**
- [ ] **What is the expected timeline/depth of review?**
- [ ] **Are there specific coding standards or guidelines to follow?**
- [ ] **Should review include dependencies or stay focused on target code?**

#### 5. **Success Criteria**
- [ ] **What would constitute a successful review outcome?**
- [ ] **Are there specific deliverables expected?**
- [ ] **Should the review include refactoring suggestions?**

---

## Phase 1: Code Discovery & Mapping

### 1.1 Structure Analysis
- [ ] **Identify all files in scope**
  - [ ] Map directory structure if reviewing a package
  - [ ] List all classes, interfaces, traits, and enums
  - [ ] Document file relationships and dependencies
  - [ ] Identify entry points and public APIs

### 1.2 Dependency Analysis
- [ ] **Map internal dependencies**
  - [ ] Document class inheritance hierarchies
  - [ ] Identify composition relationships
  - [ ] Map interface implementations
  - [ ] Trace method call chains

- [ ] **Map external dependencies**
  - [ ] List all imported packages/namespaces
  - [ ] Identify third-party library usage
  - [ ] Document framework dependencies
  - [ ] Check for circular dependencies

### 1.3 Architecture Overview
- [ ] **Document high-level architecture**
  - [ ] Identify design patterns in use
  - [ ] Map data flow through components
  - [ ] Document architectural layers
  - [ ] Identify configuration and extension points

## Phase 2: Code Quality Assessment

### 2.1 Code Structure & Organization
- [ ] **File and class organization**
  - [ ] Verify single responsibility principle adherence
  - [ ] Check appropriate file/class/method sizes
  - [ ] Assess namespace and package organization
  - [ ] Validate naming conventions consistency

- [ ] **Method and property design**
  - [ ] Review method signatures and parameters
  - [ ] Check property visibility and encapsulation
  - [ ] Assess method complexity and cohesion
  - [ ] Validate return type consistency

### 2.2 Code Readability & Maintainability
- [ ] **Naming and conventions**
  - [ ] Verify descriptive variable/method names
  - [ ] Check consistent naming patterns
  - [ ] Assess abbreviation usage appropriateness
  - [ ] Validate coding standard compliance

- [ ] **Code clarity and documentation**
  - [ ] Review inline comments quality and necessity
  - [ ] Check PHPDoc completeness and accuracy
  - [ ] Assess code self-documentation
  - [ ] Identify unclear or complex logic

### 2.3 Type Safety & Error Handling
- [ ] **Type declarations and validation**
  - [ ] Verify proper type hints usage
  - [ ] Check nullable type handling
  - [ ] Assess union/intersection type usage
  - [ ] Validate generic type parameters

- [ ] **Error handling patterns**
  - [ ] Review exception handling strategies
  - [ ] Check appropriate exception types
  - [ ] Assess error propagation patterns
  - [ ] Validate input validation and sanitization

## Phase 3: Design & Architecture Review

### 3.1 Design Principles Adherence
- [ ] **SOLID principles assessment**
  - [ ] Single Responsibility: Each class has one reason to change
  - [ ] Open/Closed: Open for extension, closed for modification
  - [ ] Liskov Substitution: Subtypes are substitutable for base types
  - [ ] Interface Segregation: Clients shouldn't depend on unused interfaces
  - [ ] Dependency Inversion: Depend on abstractions, not concretions

### 3.2 Design Pattern Implementation
- [ ] **Pattern usage evaluation**
  - [ ] Identify design patterns in use
  - [ ] Assess pattern implementation correctness
  - [ ] Check for pattern misuse or over-engineering
  - [ ] Evaluate pattern appropriateness for context

### 3.3 API Design Quality
- [ ] **Public interface assessment**
  - [ ] Review public method signatures
  - [ ] Check API consistency and intuitiveness
  - [ ] Assess backward compatibility considerations
  - [ ] Validate parameter object usage

- [ ] **Extension and customization**
  - [ ] Review extensibility mechanisms
  - [ ] Check configuration options design
  - [ ] Assess plugin/hook architectures
  - [ ] Validate dependency injection usage

## Phase 4: Security & Performance Analysis

### 4.1 Security Assessment
- [ ] **Input validation and sanitization**
  - [ ] Check all user input validation
  - [ ] Review SQL injection prevention
  - [ ] Assess XSS prevention measures
  - [ ] Validate file upload security

- [ ] **Data protection and privacy**
  - [ ] Review sensitive data handling
  - [ ] Check encryption/hashing usage
  - [ ] Assess access control implementation
  - [ ] Validate data persistence security

### 4.2 Performance Analysis
- [ ] **Algorithm efficiency**
  - [ ] Review time complexity of key algorithms
  - [ ] Identify potential performance bottlenecks
  - [ ] Assess memory usage patterns
  - [ ] Check for unnecessary computations

- [ ] **Resource management**
  - [ ] Review database query efficiency
  - [ ] Check caching implementation
  - [ ] Assess I/O operation optimization
  - [ ] Validate connection pooling usage

### 4.3 Scalability Considerations
- [ ] **Concurrent access handling**
  - [ ] Review thread safety considerations
  - [ ] Check for race conditions
  - [ ] Assess locking mechanisms
  - [ ] Validate stateless design where appropriate

## Phase 5: Testing & Quality Assurance

### 5.1 Test Coverage Analysis
- [ ] **Unit test assessment**
  - [ ] Execute existing test suites
  - [ ] Assess test coverage percentage
  - [ ] Review test quality and maintainability
  - [ ] Identify missing test scenarios

### 5.2 Test Design Quality
- [ ] **Test structure and organization**
  - [ ] Review test naming conventions
  - [ ] Check test isolation and independence
  - [ ] Assess test data management
  - [ ] Validate mock/stub usage

- [ ] **Test scenarios completeness**
  - [ ] Check happy path coverage
  - [ ] Review error condition testing
  - [ ] Assess edge case handling
  - [ ] Validate boundary condition tests

### 5.3 Integration & System Testing
- [ ] **Integration test evaluation**
  - [ ] Review component interaction tests
  - [ ] Check database integration tests
  - [ ] Assess external service integration
  - [ ] Validate end-to-end scenarios

## Phase 6: Documentation & Compliance Review

### 6.1 Code Documentation Quality
- [ ] **Inline documentation**
  - [ ] Review comment quality and relevance
  - [ ] Check PHPDoc completeness
  - [ ] Assess code example accuracy
  - [ ] Validate parameter documentation

### 6.2 External Documentation
- [ ] **Usage documentation**
  - [ ] Review README files
  - [ ] Check API documentation
  - [ ] Assess example code quality
  - [ ] Validate installation instructions

### 6.3 Compliance & Standards
- [ ] **Coding standards compliance**
  - [ ] Check PSR standard adherence
  - [ ] Review project-specific guidelines
  - [ ] Assess linting rule compliance
  - [ ] Validate formatting consistency

## Phase 7: Issue Identification & Prioritization

### 7.1 Issue Classification
- [ ] **Critical issues** (Security vulnerabilities, major bugs)
- [ ] **High priority** (Performance issues, design flaws)
- [ ] **Medium priority** (Code quality improvements)
- [ ] **Low priority** (Style improvements, minor optimizations)

### 7.2 Impact Assessment
- [ ] **Business impact evaluation**
  - [ ] Assess user experience impact
  - [ ] Review maintainability consequences
  - [ ] Check scalability implications
  - [ ] Validate security risk levels

### 7.3 Effort Estimation
- [ ] **Remediation effort assessment**
  - [ ] Estimate fix complexity for each issue
  - [ ] Identify dependencies between fixes
  - [ ] Assess testing requirements
  - [ ] Consider deployment implications

## Phase 8: Recommendations & Action Plan

### 8.1 Refactoring Recommendations
- [ ] **Structural improvements**
  - [ ] Suggest class/method reorganization
  - [ ] Recommend design pattern implementations
  - [ ] Propose interface simplifications
  - [ ] Identify code duplication elimination

### 8.2 Performance Optimizations
- [ ] **Immediate improvements**
  - [ ] Suggest algorithm optimizations
  - [ ] Recommend caching strategies
  - [ ] Propose query optimizations
  - [ ] Identify resource usage improvements

### 8.3 Security Enhancements
- [ ] **Security improvements**
  - [ ] Recommend input validation enhancements
  - [ ] Suggest encryption implementations
  - [ ] Propose access control improvements
  - [ ] Identify vulnerability patches

## Deliverables

### Primary Reports
- [ ] **Executive Summary** - High-level findings and recommendations
- [ ] **Detailed Technical Report** - Comprehensive analysis with examples
- [ ] **Issue Tracker Export** - Categorized list of all identified issues
- [ ] **Refactoring Roadmap** - Prioritized improvement plan

### Supporting Materials
- [ ] **Code Quality Metrics** - Quantitative assessment results
- [ ] **Architecture Diagrams** - Visual representation of current structure
- [ ] **Test Coverage Report** - Detailed coverage analysis
- [ ] **Performance Benchmarks** - Current performance characteristics

### Actionable Outputs
- [ ] **Quick Wins List** - Easy improvements with high impact
- [ ] **Critical Fixes** - Must-address security and stability issues
- [ ] **Long-term Improvements** - Strategic refactoring recommendations
- [ ] **Best Practices Guide** - Standards for future development

## Success Criteria

### Quality Gates
- [ ] All critical security issues identified and documented
- [ ] All major design flaws highlighted with solutions
- [ ] Test coverage gaps identified with recommendations
- [ ] Performance bottlenecks documented with optimization suggestions

### Review Completeness
- [ ] 100% of in-scope code files analyzed
- [ ] All public APIs reviewed for design quality
- [ ] All identified issues categorized and prioritized
- [ ] Actionable recommendations provided for all major findings

## Process Guidelines

### Review Approach
- **Be thorough but pragmatic** - Focus on issues that meaningfully impact code quality
- **Provide constructive feedback** - Always suggest improvements, not just identify problems
- **Consider context** - Understand business requirements and technical constraints
- **Be specific** - Provide concrete examples and code snippets where helpful

### Communication Standards
- **Use clear, professional language** appropriate for technical audience
- **Provide actionable recommendations** with specific implementation guidance
- **Prioritize findings** based on impact and effort required
- **Include positive observations** to recognize good practices implemented

### Quality Assurance
- **Validate all findings** with concrete evidence or examples
- **Test recommendations** where possible to ensure feasibility
- **Consider downstream impacts** of suggested changes
- **Maintain objectivity** while being helpful and constructive