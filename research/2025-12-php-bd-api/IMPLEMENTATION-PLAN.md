# Implementation Plan: Beads Integration for PartnerSpot

**Status**: Ready for Implementation
**Epic**: `partnerspot-heow`
**Target**: `app/Integrations/Beads/`
**Architecture**: DDD, Clean Code, Type-Safe, Layered

## Plan Overview

This document outlines the complete 3-phase implementation plan for integrating bd (beads) issue tracker and bv (beads viewer) graph analysis into PartnerSpot using Domain-Driven Design principles.

## Three-Phase Approach

### ✅ Phase 1: Specification & Design (COMPLETE)

**Task**: `partnerspot-heow.1` ✅ Closed

**Deliverables**:
- ✅ IMPLEMENTATION-SPEC.md (comprehensive specification)
- ✅ Architecture overview (4-layer DDD structure)
- ✅ Domain model design (entities, value objects, enums)
- ✅ Use case patterns (Command/Query separation)
- ✅ Type-safe patterns (no arrays, use DTOs/VOs)
- ✅ Testing strategy (unit, integration, feature)
- ✅ Success criteria (coverage, performance, static analysis)

**Reference**: `research/studies/2025-12-php-bd-api/IMPLEMENTATION-SPEC.md`

### ✅ Phase 2: Task Breakdown (COMPLETE)

**Task**: `partnerspot-heow.2` ✅ Closed

**Deliverables**:
- ✅ 36 detailed implementation tasks created
- ✅ Tasks grouped by layer (Domain, Infrastructure, Application, Presentation)
- ✅ Testing tasks (Unit, Integration, Feature)
- ✅ Quality tasks (PHPStan, Psalm, Pint, Coverage)
- ✅ Documentation tasks (PHPDoc, README)

**Task Summary**:
- **Domain Layer**: 8 tasks
- **Infrastructure Layer**: 7 tasks
- **Application Layer**: 8 tasks
- **Presentation Layer**: 4 tasks
- **Testing**: 3 tasks
- **Quality & Docs**: 6 tasks
- **Total**: 36 tasks

### 🔄 Phase 3: Peer Review & Issue Resolution (PENDING)

**Task**: `partnerspot-heow.3` ⏳ Open (blocked by implementation)

**Deliverables**:
- Present completed tasks with specifications
- Consume peer review feedback
- Address identified issues
- Final validation before production

**Blocked By**: All implementation tasks must be complete

## Implementation Tasks (36 Total)

### Domain Layer (8 tasks)

Foundation layer with pure business logic, zero dependencies:

1. **`partnerspot-heow.2.1`** - Implement value objects (TaskId, Priority, Agent)
   - TaskId: Validated bd ID format (`bd-[a-z0-9]+`)
   - Priority: 0-4 with semantic labels (critical, high, medium, low, backlog)
   - Agent: Identity value object with validation

2. **`partnerspot-heow.2.2`** - Implement enums (TaskStatus, TaskType, DependencyType)
   - TaskStatus: `open | in_progress | closed`
   - TaskType: `task | bug | feature | epic`
   - DependencyType: `blocks | related | parent | discovered-from`

3. **`partnerspot-heow.2.3`** - Implement Task entity
   - State transitions: `claim()`, `start()`, `complete()`, `block()`, `abandon()`
   - Queries: `isOpen()`, `isClosed()`, `canBeClaimed()`
   - Business rules enforcement

4. **`partnerspot-heow.2.4`** - Implement Comment entity
   - Message, author, timestamps
   - Mention detection (`@agent-id`)
   - Immutable value object

5. **`partnerspot-heow.2.5`** - Implement domain collections
   - TaskCollection: `inProgress()`, `highPriority()`, `withLabel()`
   - CommentCollection: `unread()`, `mentionsAgent()`
   - Type-safe filtering and mapping

6. **`partnerspot-heow.2.6`** - Implement repository interfaces
   - TaskRepositoryInterface: `findById()`, `findByStatus()`, `save()`
   - GraphRepositoryInterface: `getInsights()`, `getExecutionPlan()`
   - Pure contracts, no implementation

7. **`partnerspot-heow.2.7`** - Implement domain services
   - TaskLifecycleService: State transition rules
   - DependencyService: Dependency graph operations
   - SessionRecoveryService: Context recovery logic

8. **`partnerspot-heow.2.8`** - Implement domain exceptions
   - BeadsException (base)
   - TaskNotFoundException
   - InvalidStateTransitionException
   - ConcurrencyException
   - DependencyCycleException

### Infrastructure Layer (7 tasks)

Technical implementations and external integrations:

9. **`partnerspot-heow.2.9`** - Implement CommandExecutor with Sandbox
   - Use instructor-php Sandbox (HostSandbox driver)
   - ExecutionPolicy configuration
   - Timeout handling, output capping
   - Retry logic (max 3 attempts, exponential backoff)

10. **`partnerspot-heow.2.10`** - Implement BdClient (CLI wrapper)
    - Commands: `list`, `show`, `create`, `update`, `close`, `dep`, `comments`
    - JSON parsing
    - Command whitelisting (security)
    - Error handling

11. **`partnerspot-heow.2.11`** - Implement BvClient (graph analysis wrapper)
    - Commands: `--robot-insights`, `--robot-plan`, `--robot-priority`
    - Parse graph metrics (PageRank, betweenness, cycles)
    - Execution plan parsing
    - Priority recommendations

12. **`partnerspot-heow.2.12`** - Implement JSON parsers
    - TaskParser: Convert bd JSON to TaskData DTO
    - CommentParser: Convert bd comments to Comment entities
    - GraphParser: Convert bv JSON to GraphInsights VO
    - Validation and error handling

13. **`partnerspot-heow.2.13`** - Implement BdTaskRepository
    - Concrete implementation of TaskRepositoryInterface
    - Uses BdClient for operations
    - Maps between domain entities and bd format
    - Handles persistence and retrieval

14. **`partnerspot-heow.2.14`** - Implement BvGraphRepository
    - Concrete implementation of GraphRepositoryInterface
    - Uses BvClient for graph analysis
    - Maps bv output to domain value objects

15. **`partnerspot-heow.2.15`** - Implement configuration (BeadsConfig)
    - Create `config/beads.php`
    - Binary paths (`bd_binary`, `bv_binary`)
    - Executor settings (timeout, driver, retry)
    - Cache configuration

### Application Layer (8 tasks)

Use cases and orchestration:

16. **`partnerspot-heow.2.16`** - Implement ClaimTask use case
    - ClaimTaskCommand (input)
    - ClaimTaskHandler (logic)
    - ClaimTaskResult (output)
    - Validate → Apply → Persist → Return

17. **`partnerspot-heow.2.17`** - Implement CreateTask use case
    - CreateTaskCommand, CreateTaskHandler, CreateTaskResult
    - Task creation logic
    - Validation and persistence

18. **`partnerspot-heow.2.18`** - Implement CompleteTask use case
    - CompleteTaskCommand, CompleteTaskHandler, CompleteTaskResult
    - Completion logic with reason
    - State validation

19. **`partnerspot-heow.2.19`** - Implement GetNextTask query
    - GetNextTaskQuery, GetNextTaskHandler, GetNextTaskResult
    - Find highest priority unassigned ready task
    - Query optimization

20. **`partnerspot-heow.2.20`** - Implement RecoverSession query
    - RecoverSessionQuery, RecoverSessionHandler, RecoverSessionResult
    - Build SessionContext (active tasks, mentions, recommendations)
    - Context aggregation

21. **`partnerspot-heow.2.21`** - Implement CreateEpic use case
    - CreateEpicCommand, CreateEpicHandler, CreateEpicResult
    - Create epic with subtasks
    - Dependency management

22. **`partnerspot-heow.2.22`** - Implement DTOs
    - TaskData, CreateTaskData, UpdateTaskData
    - CreateEpicData, SubtaskData
    - Readonly, validated, factory methods

23. **`partnerspot-heow.2.23`** - Implement application services
    - TaskQueryService: Complex queries
    - GraphAnalysisService: Graph operations
    - AgentContextService: Agent management
    - Caching, cross-cutting concerns

### Presentation Layer (4 tasks)

User-facing API:

24. **`partnerspot-heow.2.24`** - Implement fluent TaskBuilder
    - Methods: `type()`, `priority()`, `description()`, `assignTo()`, `assignToMe()`
    - Creation: `create()`, `createAndClaim()`, `createAndStart()`
    - Delegates to use case handlers

25. **`partnerspot-heow.2.25`** - Implement fluent EpicBuilder
    - Methods: `parallelTasks()`, `sequentialTasks()`, `subtask(callback)`
    - Creates epic + subtasks with dependencies
    - Fluent configuration

26. **`partnerspot-heow.2.26`** - Implement Beads facade
    - Fluent API: `as()`, `task()`, `epic()`, `nextTask()`, `mine()`, `available()`, `find()`
    - Agent context management
    - Delegates to builders and use case handlers

27. **`partnerspot-heow.2.27`** - Implement Laravel ServiceProvider
    - Register: executor, repositories, handlers, facade
    - Configuration binding
    - Dependency injection setup

### Testing (3 tasks)

Comprehensive test coverage:

28. **`partnerspot-heow.2.28`** - Write unit tests for domain layer
    - Test Task entity state transitions
    - Test value object validation
    - Test collection filtering
    - Test domain services
    - **Target**: 90%+ coverage

29. **`partnerspot-heow.2.29`** - Write integration tests for infrastructure
    - Test BdClient with real bd commands
    - Test BvClient with real bv commands
    - Test repositories
    - Use temp directories, cleanup

30. **`partnerspot-heow.2.30`** - Write feature tests for facade
    - Test through Beads facade
    - Test claim task workflow
    - Test create epic workflow
    - Test session recovery
    - End-to-end scenarios

### Quality & Documentation (6 tasks)

Code quality and documentation:

31. **`partnerspot-heow.2.31`** - Run PHPStan level 8 and fix issues
    - Static analysis at strictest level
    - Fix type errors, missing types
    - **Target**: Zero errors

32. **`partnerspot-heow.2.32`** - Run Psalm level 1 and fix issues
    - Static analysis with Psalm
    - Fix all errors
    - **Target**: Zero errors

33. **`partnerspot-heow.2.33`** - Run Laravel Pint and format code
    - PSR-12 compliance
    - Consistent code style
    - All files formatted

34. **`partnerspot-heow.2.34`** - Ensure test coverage >90%
    - Run coverage analysis
    - Add tests for uncovered code
    - **Target**: 90%+ coverage

35. **`partnerspot-heow.2.35`** - Add PHPDoc to all public methods
    - Document parameters (`@param`)
    - Document return types (`@return`)
    - Document exceptions (`@throws`)
    - IDE-friendly documentation

36. **`partnerspot-heow.2.36`** - Create usage examples and README
    - Create `app/Integrations/Beads/README.md`
    - Usage examples
    - Architecture overview
    - Getting started guide

## Implementation Order

**Recommended sequence** (follows dependency graph):

### Stage 1: Foundation (Tasks 1-8)
- Domain layer implementation
- Pure business logic, no dependencies
- Can be implemented in parallel

### Stage 2: Technical Infrastructure (Tasks 9-15)
- Infrastructure layer implementation
- Depends on domain contracts
- Can be mostly parallel

### Stage 3: Use Cases (Tasks 16-23)
- Application layer implementation
- Depends on domain + infrastructure
- Some sequencing required

### Stage 4: API Layer (Tasks 24-27)
- Presentation layer implementation
- Depends on all previous layers
- Must be sequential (facade depends on builders)

### Stage 5: Testing (Tasks 28-30)
- Test implementation
- After corresponding implementation
- Can be parallel per layer

### Stage 6: Quality & Docs (Tasks 31-36)
- After all implementation complete
- Some tasks must be sequential (fix before coverage)
- Documentation last

## Success Criteria

All criteria from IMPLEMENTATION-SPEC.md must be met:

1. ✅ **Type Safety**: Zero use of arrays for structured data
2. ✅ **Domain Purity**: Domain layer has zero framework dependencies
3. ✅ **Clean Architecture**: Clear layer separation, dependencies flow inward
4. ✅ **Testability**: 90%+ test coverage
5. ✅ **Performance**: <100ms for read operations, <200ms for writes
6. ✅ **DX**: Fluent API matches DX-SHOWCASE.md examples
7. ✅ **Static Analysis**: PHPStan level 8, Psalm level 1
8. ✅ **Code Style**: Laravel Pint passes, PSR-12 compliant

## Task Dependencies

### Critical Path

```
Phase 1 (Spec) → Phase 2 (Tasks) → Implementation → Phase 3 (Review)
                                        ↓
                    Domain → Infrastructure → Application → Presentation
                      ↓            ↓              ↓              ↓
                   Tests → Integration Tests → Feature Tests → Quality
```

### Layer Dependencies

- **Infrastructure** depends on **Domain** (interfaces)
- **Application** depends on **Domain** + **Infrastructure**
- **Presentation** depends on **Application**
- **Tests** depend on corresponding layer
- **Quality** depends on all implementation

## Execution Strategy

### For Human Development Team

1. **Review specification** (`IMPLEMENTATION-SPEC.md`)
2. **Assign tasks** by layer (domain → infra → app → presentation)
3. **Implement in order** (respect dependencies)
4. **Write tests** alongside implementation
5. **Run quality checks** continuously
6. **Peer review** before Phase 3

### For AI Agent Collaboration

```php
// Agent claims and executes tasks
Beads::as('agent-developer');

$task = Beads::find('partnerspot-heow.2.1'); // Value objects
$task->claim()
     ->comment('Starting implementation of value objects')
     ->start();

// Implement TaskId, Priority, Agent
implementValueObjects($task);

// Write tests
$testTask = $task->createSubtask('[test] Unit tests for value objects')
    ->assignToMe()
    ->createAndClaim();

writeTests($testTask);
$testTask->complete('Tests written, all passing');

// Complete main task
$task->complete('Value objects implemented: TaskId, Priority, Agent');

// Move to next task
$nextTask = Beads::nextTask();
```

## References

### Research Documents

- **Main Specification**: `research/studies/2025-12-php-bd-api/IMPLEMENTATION-SPEC.md`
- **DX Showcase**: `research/studies/2025-12-php-bd-api/DX-SHOWCASE.md`
- **Enhanced API**: `research/studies/2025-12-php-bd-api/enhanced-api.php`
- **Agent Collaboration**: `research/studies/2025-12-php-bd-api/AGENT-COLLABORATION.md`
- **Sandbox Analysis**: `research/studies/2025-12-php-bd-api/ADDENDUM.md`
- **Laravel Integration**: `research/studies/2025-12-php-bd-api/laravel-integration.md`
- **Security Analysis**: `research/studies/2025-12-php-bd-api/security-analysis.md`

### bd Commands

```bash
# View epic and tasks
bd show partnerspot-heow
bd list --json | grep partnerspot-heow

# Track progress
bd stats
bd ready --limit 10

# Claim and work on task
bd update partnerspot-heow.2.1 --status=in_progress
bd comments add partnerspot-heow.2.1 "Implementation notes..."
bd close partnerspot-heow.2.1 --reason="Complete"

# Sync changes
bd sync
```

## Current Status

- ✅ **Phase 1**: Complete (specification created)
- ✅ **Phase 2**: Complete (36 tasks created)
- ⏳ **Phase 3**: Pending (awaits implementation)
- 🔄 **Implementation**: Ready to start (36 tasks open)

## Next Steps

1. **Assign tasks** to development team or AI agents
2. **Start with domain layer** (tasks 1-8)
3. **Write tests** alongside implementation
4. **Run quality checks** after each task
5. **Complete all 36 tasks**
6. **Proceed to Phase 3** peer review

## Estimated Timeline

Based on task breakdown:

- **Domain Layer**: 2 days (8 tasks)
- **Infrastructure Layer**: 2 days (7 tasks)
- **Application Layer**: 2 days (8 tasks)
- **Presentation Layer**: 1 day (4 tasks)
- **Testing**: 2 days (3 tasks, comprehensive)
- **Quality & Docs**: 1 day (6 tasks)

**Total**: 10 days (with buffer)

**Parallel execution**: 5-6 days (multiple developers/agents)

---

**Implementation Plan Created**: 2025-12-01
**Status**: Ready for execution
**Epic**: `partnerspot-heow` - Implement Beads Integration for PartnerSpot
