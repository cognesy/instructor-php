# Laravel Job Integration: Agent Serialization & Blueprint Architecture

**Date**: 2025-01-06
**Status**: Design Review - Awaiting Senior Dev Team Approval
**Priority**: CRITICAL - Blocking Laravel deployment model

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [The Core Problem](#the-core-problem)
3. [Laravel Job Constraints](#laravel-job-constraints)
4. [Revised Architecture](#revised-architecture)
5. [Agent Blueprint System](#agent-blueprint-system)
6. [Implementation Plan](#implementation-plan)
7. [Critical Design Decisions](#critical-design-decisions)
8. [Open Questions for Review](#open-questions-for-review)

---

## Executive Summary

### The Challenge

Agents need to work in Laravel jobs/workers for async execution, requiring:
- **Serialization** - Store agent definitions in queue (Redis/DB)
- **Reconstruction** - Rebuild exact agent in worker process
- **Resumability** - Pause for human input, then continue
- **Discovery** - Find available agents dynamically

### The Solution

**Agent Blueprint Registry Pattern**
- Blueprints = Serializable agent specifications (references, not instances)
- Registry = Central store for blueprint definitions
- Factory = Reconstructs agents from blueprints + dependencies
- Repository = Unified interface to static/dynamic/file-based blueprints

### Key Insight

**Jobs only serialize blueprint IDs, not agent instances.**
Worker reconstructs agent from blueprint + service container dependencies.

---

## The Core Problem

### Current Broken Model

```php
// ❌ Define agent inline - doesn't work with jobs
$agent = AgentBuilder::base()
    ->withSystemPrompt('You are a code reviewer...')
    ->withTools(
        new ReadFileTool(),           // Tool instance - not serializable
        new WriteFileTool(),          // Tool instance - not serializable
        new CustomTool(fn() => ...)   // Closure - not serializable
    )
    ->withCapability(new UseSubagents($registry)) // State - not serializable
    ->build();

// ❌ Can't serialize this for job queue
dispatch(new ExecuteAgentJob($agent)); // FAILS
```

**Why this fails:**
- Tool instances contain closures, non-serializable state
- Capabilities hold references to services/registries
- Agent object is deeply nested with non-serializable dependencies
- Laravel can't serialize closures or resource handles

### What Happens in Laravel Jobs

```
1. Main Process: dispatch(new Job($data))
   ↓
2. Serialization: serialize($job) → JSON/binary
   ↓
3. Queue Storage: Redis/Database stores serialized job
   ↓
4. Worker Process: Picks job from queue (different process/server!)
   ↓
5. Deserialization: unserialize($job)
   ↓
6. Dependency Injection: Service container resolves handle() dependencies
   ↓
7. Execution: $job->handle(...)
```

**Critical constraint**: Worker has **no shared memory** with dispatch process.
Must reconstruct agent from scratch using only serializable data.

---

## Laravel Job Constraints

### What CAN Be Serialized

✅ **Primitives**: strings, integers, floats, booleans
✅ **Arrays**: of serializable values
✅ **Plain objects**: with only serializable properties
✅ **Eloquent models**: via SerializesModels trait
✅ **Simple DTOs**: pure data classes

### What CANNOT Be Serialized

❌ **Closures**: `fn() => ...`, `function() { ... }`
❌ **Resource handles**: database connections, file handles
❌ **Service instances**: with complex state
❌ **Tool instances**: contain closures in most cases
❌ **Registry instances**: hold runtime state

### Implication for Agent System

**Must serialize**: Blueprint ID (string)
**Must reconstruct**: Everything else from services

```php
// ✅ This works
dispatch(new ExecuteAgentJob(
    blueprintId: 'code-reviewer',  // ← Just a string
    state: [...],                   // ← Plain array
    input: [...]                    // ← Plain array
));

// Worker reconstructs:
$blueprint = $blueprintRepo->find('code-reviewer');  // From service
$agent = $factory->fromBlueprint($blueprint);        // Fresh instance
```

---

## Revised Architecture

### Priority Shift: Before vs After

#### Before (Tool Discovery Focus)

```
Priority 1: Tool Discovery (tool.list, tool.describe)
Priority 2: Skills as Tools
Priority 3: Namespace Support
Priority 4: Semantic Search
```

**Problem**: Doesn't address serialization at all.

#### After (Laravel Job Focus)

```
Priority 1: ToolRegistry with Reconstruction ⭐ CRITICAL
Priority 2: AgentBlueprint System ⭐ CRITICAL
Priority 3: BlueprintRepository (multi-source) ⭐ CRITICAL
Priority 4: AgentFactory (reconstruction) ⭐ CRITICAL
Priority 5: ExecuteAgentJob Implementation ⭐ CRITICAL
Priority 6: Skills as Tools (naturally serializable) ⭐ HIGH
Priority 7: Tool Discovery (builds on registry) ⭐ HIGH
Priority 8: Namespace Support (organization) ⭐ MEDIUM
```

**Rationale**: Can't deploy to Laravel without serialization working.

### Architectural Layers

```
┌─────────────────────────────────────────────────────────┐
│                     Job Queue Layer                      │
│  ExecuteAgentJob(blueprintId: 'code-reviewer')          │
└─────────────────────────────────────────────────────────┘
                         ↓ serialize/deserialize
┌─────────────────────────────────────────────────────────┐
│                  Blueprint Layer                         │
│  BlueprintRepository → AgentBlueprint (serializable)     │
└─────────────────────────────────────────────────────────┘
                         ↓ reconstruct
┌─────────────────────────────────────────────────────────┐
│                   Factory Layer                          │
│  AgentFactory + ToolRegistry + SkillLibrary              │
└─────────────────────────────────────────────────────────┘
                         ↓ build
┌─────────────────────────────────────────────────────────┐
│                    Runtime Layer                         │
│  Agent Instance (tools, capabilities, state)             │
└─────────────────────────────────────────────────────────┘
```

---

## Agent Blueprint System

### 1. AgentBlueprint (Serializable Specification)

```php
/**
 * Fully serializable agent specification.
 * Contains only references (names/IDs), never instances.
 */
class AgentBlueprint
{
    public function __construct(
        public string $id,              // Unique identifier (e.g., 'code-reviewer')
        public string $name,            // Human-readable name
        public string $description,     // What this agent does
        public string $systemPrompt,    // Base system prompt
        public array $toolNames,        // ['file.read', 'file.write'] - NOT instances
        public array $skillNames,       // ['code-review', 'security-audit'] - NOT instances
        public array $capabilityNames,  // ['UseSubagents', 'UseSelfCritique'] - NOT instances
        public array $config,           // Additional configuration (serializable)
        public array $metadata,         // Tags, category, version, author, etc.
    ) {}

    // Serialization methods
    public function toArray(): array;
    public static function fromArray(array $data): self;
    public function toJson(): string;
    public static function fromJson(string $json): self;
}
```

**Key principle**: Blueprint contains ZERO non-serializable data.

### 2. BlueprintRepository (Storage Interface)

```php
/**
 * Unified interface for storing/retrieving blueprints.
 * Implementations can use different storage backends.
 */
interface BlueprintRepository
{
    /** Find blueprint by ID (returns null if not found) */
    public function find(string $id): ?AgentBlueprint;

    /** Check if blueprint exists */
    public function has(string $id): bool;

    /** List all blueprints (returns metadata only, not full specs) */
    public function all(): array;

    /** Save or update blueprint */
    public function save(AgentBlueprint $blueprint): void;

    /** Delete blueprint */
    public function delete(string $id): void;
}
```

### 3. Multiple Blueprint Sources

#### a) InMemoryBlueprintRepository (Static Registration)

```php
/**
 * Holds blueprints registered at boot time (AppServiceProvider).
 * Fast, no I/O, but not persistent.
 */
class InMemoryBlueprintRepository implements BlueprintRepository
{
    private array $blueprints = [];

    public function register(string $id, AgentBlueprint $blueprint): void {
        $this->blueprints[$id] = $blueprint;
    }

    public function find(string $id): ?AgentBlueprint {
        return $this->blueprints[$id] ?? null;
    }

    public function all(): array {
        return array_map(
            fn($bp) => [
                'id' => $bp->id,
                'name' => $bp->name,
                'description' => $bp->description,
                'metadata' => $bp->metadata,
            ],
            $this->blueprints
        );
    }
}
```

**Use case**: Core system agents (code-reviewer, security-analyst, etc.)
**Registered in**: `AgentServiceProvider::boot()`

#### b) DatabaseBlueprintRepository (Dynamic Creation)

```php
/**
 * Stores user-created blueprints in database.
 * Allows runtime creation via UI/API.
 */
class DatabaseBlueprintRepository implements BlueprintRepository
{
    public function find(string $id): ?AgentBlueprint {
        $model = AgentBlueprintModel::find($id);
        return $model ? AgentBlueprint::fromArray($model->blueprint_data) : null;
    }

    public function save(AgentBlueprint $blueprint): void {
        AgentBlueprintModel::updateOrCreate(
            ['id' => $blueprint->id],
            [
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'blueprint_data' => $blueprint->toArray(),
            ]
        );
    }

    public function all(): array {
        return AgentBlueprintModel::all()->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'description' => $m->description,
            'metadata' => $m->blueprint_data['metadata'] ?? [],
        ])->toArray();
    }
}
```

**Use case**: Custom agents created by users
**Created via**: UI/API endpoints

#### c) FilesystemBlueprintRepository (AGENT.md Files)

```php
/**
 * Loads blueprints from existing AGENT.md files.
 * Provides backward compatibility with current AgentRegistry.
 */
class FilesystemBlueprintRepository implements BlueprintRepository
{
    public function __construct(
        private AgentRegistry $agentRegistry,
        private string $agentPath = './packages/addons/agents'
    ) {}

    public function find(string $id): ?AgentBlueprint {
        $spec = $this->agentRegistry->getSpec($id);
        return $spec ? $this->convertSpecToBlueprint($spec) : null;
    }

    public function all(): array {
        return array_map(
            fn($spec) => [
                'id' => $spec->name,
                'name' => $spec->name,
                'description' => $spec->description,
                'metadata' => ['source' => 'filesystem'],
            ],
            $this->agentRegistry->listSpecs()
        );
    }

    private function convertSpecToBlueprint(AgentSpec $spec): AgentBlueprint {
        return new AgentBlueprint(
            id: $spec->name,
            name: $spec->name,
            description: $spec->description,
            systemPrompt: $spec->systemPrompt,
            toolNames: $spec->tools ?? [],
            skillNames: $spec->skills ?? [],
            capabilityNames: $spec->capabilities ?? [],
            config: [],
            metadata: ['source' => 'filesystem', 'path' => $spec->path],
        );
    }
}
```

**Use case**: Existing AGENT.md files in codebase
**Located in**: `packages/addons/agents/`, `skills/`, etc.

#### d) CompositeBlueprintRepository (Unified Access)

```php
/**
 * Checks multiple sources in priority order.
 * Provides single unified interface to all blueprint sources.
 */
class CompositeBlueprintRepository implements BlueprintRepository
{
    public function __construct(
        private InMemoryBlueprintRepository $memory,
        private DatabaseBlueprintRepository $database,
        private FilesystemBlueprintRepository $filesystem,
    ) {}

    public function find(string $id): ?AgentBlueprint {
        // Priority: memory → database → filesystem
        return $this->memory->find($id)
            ?? $this->database->find($id)
            ?? $this->filesystem->find($id);
    }

    public function all(): array {
        // Merge all sources (dedup by ID, priority order)
        $all = [];

        foreach ($this->memory->all() as $meta) {
            $all[$meta['id']] = $meta;
        }

        foreach ($this->database->all() as $meta) {
            if (!isset($all[$meta['id']])) {
                $all[$meta['id']] = $meta;
            }
        }

        foreach ($this->filesystem->all() as $meta) {
            if (!isset($all[$meta['id']])) {
                $all[$meta['id']] = $meta;
            }
        }

        return array_values($all);
    }
}
```

**Use case**: Single service injected into jobs/controllers
**Resolves**: Checks all sources transparently

### 4. ToolRegistry (Reconstruction Support)

```php
/**
 * Central registry for tools with factory-based reconstruction.
 * Enables rebuilding tools from names in worker processes.
 */
class ToolRegistry
{
    private array $tools = [];          // Cached tool instances
    private array $toolFactories = [];  // Factory functions for reconstruction

    /**
     * Register a tool instance (cached for reuse)
     */
    public function register(string $name, ToolInterface $tool): void {
        $this->tools[$name] = $tool;
    }

    /**
     * Register a factory function (for reconstruction)
     * Factory receives ToolRegistry as parameter for nested dependencies
     */
    public function registerFactory(string $name, callable $factory): void {
        $this->toolFactories[$name] = $factory;
    }

    /**
     * Get tool by name (creates from factory if needed)
     */
    public function get(string $name): ToolInterface {
        // Return cached instance if available
        if (isset($this->tools[$name])) {
            return $this->tools[$name];
        }

        // Create from factory if available
        if (isset($this->toolFactories[$name])) {
            $tool = ($this->toolFactories[$name])($this);
            $this->tools[$name] = $tool; // Cache it
            return $tool;
        }

        throw new ToolNotFoundException("Tool '{$name}' not found in registry");
    }

    /**
     * Check if tool exists (in cache or factory)
     */
    public function has(string $name): bool {
        return isset($this->tools[$name]) || isset($this->toolFactories[$name]);
    }

    /**
     * List all available tool names
     */
    public function names(): array {
        return array_unique(array_merge(
            array_keys($this->tools),
            array_keys($this->toolFactories)
        ));
    }

    /**
     * List tool metadata (for discovery)
     */
    public function listMetadata(?string $namespace = null): array {
        $metadata = [];

        foreach ($this->names() as $name) {
            // Skip if namespace filter provided and doesn't match
            if ($namespace !== null && !str_starts_with($name, $namespace . '.')) {
                continue;
            }

            $tool = $this->get($name);
            $metadata[] = $tool->metadata();
        }

        return $metadata;
    }
}
```

**Key feature**: Factory pattern allows reconstruction in worker process.

### 5. AgentFactory (Agent Reconstruction)

```php
/**
 * Reconstructs agent instances from blueprints.
 * Resolves all dependencies from service container.
 */
class AgentFactory
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private SkillLibrary $skillLibrary,
        private BlueprintRepository $blueprintRepository,
    ) {}

    /**
     * Create agent from blueprint
     */
    public function fromBlueprint(AgentBlueprint $blueprint): Agent
    {
        $tools = [];

        // Reconstruct tools from registry
        foreach ($blueprint->toolNames as $toolName) {
            if (!$this->toolRegistry->has($toolName)) {
                throw new ToolNotFoundException("Tool '{$toolName}' required by blueprint '{$blueprint->id}' not found");
            }
            $tools[] = $this->toolRegistry->get($toolName);
        }

        // Reconstruct skills as tools
        foreach ($blueprint->skillNames as $skillName) {
            $skill = $this->skillLibrary->getSkill($skillName);
            if (!$skill) {
                throw new SkillNotFoundException("Skill '{$skillName}' required by blueprint '{$blueprint->id}' not found");
            }
            $tools[] = new SkillTool($skill);
        }

        // Build agent
        $builder = AgentBuilder::base()
            ->withSystemPrompt($blueprint->systemPrompt)
            ->withTools($tools);

        // Add capabilities
        foreach ($blueprint->capabilityNames as $capabilityName) {
            $builder = $this->addCapability($builder, $capabilityName, $blueprint->config);
        }

        return $builder->build();
    }

    /**
     * Add capability to builder based on name
     */
    private function addCapability(AgentBuilder $builder, string $name, array $config): AgentBuilder
    {
        return match($name) {
            'UseSubagents' => $builder->withCapability(
                new UseSubagents($this->blueprintRepository)
            ),
            'UseSelfCritique' => $builder->withCapability(
                new UseSelfCritique($config['critique_config'] ?? [])
            ),
            'UseSkills' => $builder->withCapability(
                new UseSkills($this->skillLibrary)
            ),
            default => throw new UnknownCapabilityException("Unknown capability: {$name}"),
        };
    }

    /**
     * Create agent from blueprint ID (convenience method)
     */
    public function fromBlueprintId(string $blueprintId): Agent
    {
        $blueprint = $this->blueprintRepository->find($blueprintId);
        if (!$blueprint) {
            throw new BlueprintNotFoundException("Blueprint '{$blueprintId}' not found");
        }
        return $this->fromBlueprint($blueprint);
    }
}
```

**Key feature**: Worker can rebuild entire agent from just blueprint ID.

### 6. Laravel Service Provider Registration

```php
// app/Providers/AgentServiceProvider.php

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register repositories
        $this->app->singleton(InMemoryBlueprintRepository::class);
        $this->app->singleton(DatabaseBlueprintRepository::class);

        $this->app->singleton(FilesystemBlueprintRepository::class, function ($app) {
            return new FilesystemBlueprintRepository(
                agentRegistry: $app->make(AgentRegistry::class),
                agentPath: base_path('packages/addons/agents')
            );
        });

        // Register composite repository as primary interface
        $this->app->singleton(BlueprintRepository::class, function ($app) {
            return new CompositeBlueprintRepository(
                memory: $app->make(InMemoryBlueprintRepository::class),
                database: $app->make(DatabaseBlueprintRepository::class),
                filesystem: $app->make(FilesystemBlueprintRepository::class),
            );
        });

        // Register tool registry
        $this->app->singleton(ToolRegistry::class);

        // Register skill library
        $this->app->singleton(SkillLibrary::class, function ($app) {
            return SkillLibrary::inDirectory(base_path('skills'));
        });

        // Register agent factory
        $this->app->singleton(AgentFactory::class);
    }

    /**
     * Bootstrap services (register static blueprints)
     */
    public function boot(
        InMemoryBlueprintRepository $blueprintRepo,
        ToolRegistry $toolRegistry,
        SkillLibrary $skillLibrary
    ): void {
        // Register core tools
        $this->registerCoreTools($toolRegistry);

        // Register skills as tools
        $this->registerSkillTools($toolRegistry, $skillLibrary);

        // Register static agent blueprints
        $this->registerAgentBlueprints($blueprintRepo);
    }

    /**
     * Register core tools in registry
     */
    private function registerCoreTools(ToolRegistry $registry): void
    {
        // Register tool factories (for reconstruction)
        $registry->registerFactory('file.read', fn() => new ReadFileTool());
        $registry->registerFactory('file.write', fn() => new WriteFileTool());
        $registry->registerFactory('file.search', fn() => new SearchFilesTool());
        $registry->registerFactory('file.edit', fn() => new EditFileTool());

        // Discovery tools
        $registry->registerFactory('tool.list', fn($reg) => new ToolListTool($reg));
        $registry->registerFactory('tool.describe', fn($reg) => new ToolDescribeTool($reg));

        // Skill tools
        $registry->registerFactory('load_skill', function() {
            return new LoadSkillTool(app(SkillLibrary::class));
        });
    }

    /**
     * Register skills as task.* tools
     */
    private function registerSkillTools(ToolRegistry $registry, SkillLibrary $library): void
    {
        foreach ($library->listSkills() as $skillMeta) {
            $skillName = $skillMeta['name'];
            $registry->registerFactory(
                "task.{$skillName}",
                fn() => new SkillTool($library->getSkill($skillName))
            );
        }
    }

    /**
     * Register static agent blueprints
     */
    private function registerAgentBlueprints(InMemoryBlueprintRepository $repo): void
    {
        // Code Reviewer
        $repo->register('code-reviewer', new AgentBlueprint(
            id: 'code-reviewer',
            name: 'Code Reviewer',
            description: 'Reviews code for quality, bugs, and security issues',
            systemPrompt: <<<'PROMPT'
You are an expert code reviewer with deep knowledge of software engineering best practices.
Your role is to analyze code for:
- Code quality and maintainability
- Potential bugs and edge cases
- Security vulnerabilities
- Performance issues
- Adherence to best practices
PROMPT,
            toolNames: ['file.read', 'file.search', 'task.analyze'],
            skillNames: ['code-review'],
            capabilityNames: ['UseSubagents'],
            config: ['max_depth' => 3, 'focus_areas' => ['quality', 'security', 'performance']],
            metadata: [
                'category' => 'development',
                'version' => '1.0',
                'tags' => ['code-quality', 'security', 'review'],
            ],
        ));

        // Security Analyst
        $repo->register('security-analyst', new AgentBlueprint(
            id: 'security-analyst',
            name: 'Security Analyst',
            description: 'Analyzes code for security vulnerabilities and compliance',
            systemPrompt: <<<'PROMPT'
You are a security expert specializing in application security.
Your role is to identify:
- SQL injection vulnerabilities
- XSS vulnerabilities
- Authentication/authorization issues
- Cryptographic weaknesses
- Dependency vulnerabilities
PROMPT,
            toolNames: ['file.read', 'file.search'],
            skillNames: ['security-audit'],
            capabilityNames: [],
            config: ['severity_threshold' => 'high'],
            metadata: [
                'category' => 'security',
                'version' => '1.0',
                'tags' => ['security', 'vulnerability', 'audit'],
            ],
        ));

        // Feature Planner
        $repo->register('feature-planner', new AgentBlueprint(
            id: 'feature-planner',
            name: 'Feature Planner',
            description: 'Plans implementation strategy for new features',
            systemPrompt: <<<'PROMPT'
You are a software architect and technical planner.
Your role is to design implementation plans for features including:
- Breaking down requirements into tasks
- Identifying dependencies
- Suggesting architecture patterns
- Estimating complexity
- Identifying risks
PROMPT,
            toolNames: ['file.read', 'file.search', 'task.explore'],
            skillNames: ['feature-planning'],
            capabilityNames: ['UseSubagents'],
            config: [],
            metadata: [
                'category' => 'planning',
                'version' => '1.0',
                'tags' => ['planning', 'architecture', 'design'],
            ],
        ));
    }
}
```

### 7. Laravel Job Implementation

```php
<?php

namespace App\Jobs;

use App\Models\Conversation;use Cognesy\Addons\Agent\AgentFactory;use Cognesy\Addons\Agent\Core\Data\AgentState;use Cognesy\Addons\Agent\Exceptions\BlueprintNotFoundException;use Cognesy\Addons\Agent\Registry\BlueprintRepository;use Illuminate\Bus\Queueable;use Illuminate\Contracts\Queue\ShouldQueue;use Illuminate\Foundation\Bus\Dispatchable;use Illuminate\Queue\InteractsWithQueue;use Illuminate\Queue\SerializesModels;

class ExecuteAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout (10 minutes)
     */
    public int $timeout = 600;

    /**
     * Max retry attempts
     */
    public int $tries = 3;

    /**
     * Create new job instance
     *
     * @param string $blueprintId Agent blueprint identifier
     * @param array $agentState Serialized agent state (for resume)
     * @param array $userInput User's request/input
     * @param string|null $conversationId Conversation ID (for tracking)
     */
    public function __construct(
        public string $blueprintId,
        public array $agentState,
        public array $userInput,
        public ?string $conversationId = null,
    ) {}

    /**
     * Execute the job
     *
     * Dependencies resolved from service container
     */
    public function handle(
        AgentFactory $factory,
        BlueprintRepository $blueprints
    ): void {
        // 1. Find blueprint
        $blueprint = $blueprints->find($this->blueprintId);
        if (!$blueprint) {
            throw new BlueprintNotFoundException(
                "Blueprint '{$this->blueprintId}' not found"
            );
        }

        // 2. Reconstruct agent from blueprint
        $agent = $factory->fromBlueprint($blueprint);

        // 3. Restore state if resuming
        if (!empty($this->agentState)) {
            $state = AgentState::fromArray($this->agentState);
            $agent->restoreState($state);
        }

        // 4. Execute agent
        $result = $agent->execute($this->userInput);

        // 5. Store result
        $this->storeResult($result);

        // 6. Handle continuation
        if ($result->needsHumanInput()) {
            // Agent needs human input - save state for resume
            $this->saveStateForResume($agent->getState());
        } elseif ($result->shouldContinue()) {
            // Agent wants to continue - dispatch continuation job
            $this->dispatchContinuation($agent->getState(), $result->getNextInput());
        }
    }

    /**
     * Store agent result in conversation
     */
    private function storeResult($result): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result->getOutput(),
            'metadata' => [
                'blueprint_id' => $this->blueprintId,
                'tool_calls' => $result->getToolCalls(),
                'timestamp' => now(),
            ],
        ]);
    }

    /**
     * Save agent state for later resume
     */
    private function saveStateForResume(AgentState $state): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $conversation->update([
            'status' => 'awaiting_input',
            'last_state' => $state->toArray(),
            'blueprint_id' => $this->blueprintId,
        ]);
    }

    /**
     * Dispatch continuation job
     */
    private function dispatchContinuation(AgentState $state, array $nextInput): void
    {
        dispatch(new ExecuteAgentJob(
            blueprintId: $this->blueprintId,
            agentState: $state->toArray(),
            userInput: $nextInput,
            conversationId: $this->conversationId,
        ));
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $conversation->update([
            'status' => 'failed',
            'error' => [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'timestamp' => now(),
            ],
        ]);
    }
}
```

### 8. Dispatching Jobs

```php
// Simple dispatch (new conversation)
dispatch(new ExecuteAgentJob(
    blueprintId: 'code-reviewer',
    agentState: [],
    userInput: ['query' => 'Review src/Authentication module']
));

// With conversation tracking
$conversation = Conversation::create([
    'user_id' => auth()->id(),
    'blueprint_id' => 'code-reviewer',
    'status' => 'processing',
]);

dispatch(new ExecuteAgentJob(
    blueprintId: 'code-reviewer',
    agentState: [],
    userInput: ['query' => 'Review src/Authentication module'],
    conversationId: $conversation->id
));

// Resume from saved state (user provided clarification)
$conversation = Conversation::find($conversationId);

dispatch(new ExecuteAgentJob(
    blueprintId: $conversation->blueprint_id,
    agentState: $conversation->last_state,
    userInput: ['clarification' => 'Focus on SQL injection risks'],
    conversationId: $conversation->id
));

// Spawn subagent (from UseSubagents capability)
class UseSubagents {
    public function spawnSubagent(string $blueprintId, array $input): void {
        dispatch(new ExecuteAgentJob(
            blueprintId: $blueprintId,
            agentState: [],
            userInput: $input
        ));
    }
}
```

### 9. Discovery API

```php
// List available agents
Route::get('/api/agents', function (BlueprintRepository $repo) {
    return response()->json([
        'agents' => $repo->all()
    ]);
});

// Get specific agent details
Route::get('/api/agents/{id}', function (string $id, BlueprintRepository $repo) {
    $blueprint = $repo->find($id);

    if (!$blueprint) {
        return response()->json(['error' => 'Agent not found'], 404);
    }

    return response()->json([
        'agent' => $blueprint->toArray()
    ]);
});

// Create custom agent
Route::post('/api/agents', function (Request $request, BlueprintRepository $repo) {
    $validated = $request->validate([
        'name' => 'required|string',
        'description' => 'required|string',
        'system_prompt' => 'required|string',
        'tool_names' => 'required|array',
        'skill_names' => 'array',
    ]);

    $blueprint = new AgentBlueprint(
        id: Str::uuid()->toString(),
        name: $validated['name'],
        description: $validated['description'],
        systemPrompt: $validated['system_prompt'],
        toolNames: $validated['tool_names'],
        skillNames: $validated['skill_names'] ?? [],
        capabilityNames: [],
        config: [],
        metadata: ['created_by' => auth()->id(), 'created_at' => now()],
    );

    $repo->save($blueprint);

    return response()->json(['agent' => $blueprint->toArray()], 201);
});

// Execute agent
Route::post('/api/agents/{id}/execute', function (string $id, Request $request) {
    $validated = $request->validate([
        'input' => 'required|array',
    ]);

    $conversation = Conversation::create([
        'user_id' => auth()->id(),
        'blueprint_id' => $id,
        'status' => 'processing',
    ]);

    dispatch(new ExecuteAgentJob(
        blueprintId: $id,
        agentState: [],
        userInput: $validated['input'],
        conversationId: $conversation->id
    ));

    return response()->json(['conversation_id' => $conversation->id], 202);
});

// Resume conversation (provide human input)
Route::post('/api/conversations/{id}/resume', function (string $id, Request $request) {
    $conversation = Conversation::findOrFail($id);

    if ($conversation->status !== 'awaiting_input') {
        return response()->json(['error' => 'Conversation not awaiting input'], 400);
    }

    $validated = $request->validate([
        'input' => 'required|array',
    ]);

    dispatch(new ExecuteAgentJob(
        blueprintId: $conversation->blueprint_id,
        agentState: $conversation->last_state,
        userInput: $validated['input'],
        conversationId: $conversation->id
    ));

    $conversation->update(['status' => 'processing']);

    return response()->json(['message' => 'Conversation resumed'], 200);
});
```

### 10. Database Schema

```php
// Migration for agent_blueprints table
Schema::create('agent_blueprints', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description');
    $table->json('blueprint_data'); // Full AgentBlueprint serialized
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();

    $table->index('name');
});

// Migration for conversations table
Schema::create('conversations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('blueprint_id'); // References blueprint (no FK - multi-source)
    $table->enum('status', ['processing', 'awaiting_input', 'completed', 'failed']);
    $table->json('last_state')->nullable(); // Serialized AgentState
    $table->json('error')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index('blueprint_id');
});

// Migration for conversation_messages table
Schema::create('conversation_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('conversation_id')->constrained()->onDelete('cascade');
    $table->enum('role', ['user', 'assistant', 'system']);
    $table->text('content');
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index('conversation_id');
});
```

---

## Implementation Plan

### Week 1: Foundation (CRITICAL)

#### Day 1-2: Core Classes

**Files to create:**
- `packages/addons/src/Agent/Blueprint/AgentBlueprint.php`
- `packages/addons/src/Agent/Blueprint/BlueprintRepository.php` (interface)
- `packages/addons/src/Agent/Blueprint/InMemoryBlueprintRepository.php`
- `packages/addons/src/Agent/Tools/ToolRegistry.php`
- `packages/addons/src/Agent/Exceptions/BlueprintNotFoundException.php`
- `packages/addons/src/Agent/Exceptions/ToolNotFoundException.php`

**Deliverables:**
- ✅ AgentBlueprint with serialization
- ✅ BlueprintRepository interface
- ✅ InMemoryBlueprintRepository
- ✅ ToolRegistry with factory support
- ✅ Basic tests

**Estimate**: 12-16 hours

#### Day 3-4: Repository Implementations

**Files to create:**
- `packages/addons/src/Agent/Blueprint/DatabaseBlueprintRepository.php`
- `packages/addons/src/Agent/Blueprint/FilesystemBlueprintRepository.php`
- `packages/addons/src/Agent/Blueprint/CompositeBlueprintRepository.php`
- Database migration for `agent_blueprints` table

**Deliverables:**
- ✅ DatabaseBlueprintRepository
- ✅ FilesystemBlueprintRepository (integrates with existing AgentRegistry)
- ✅ CompositeBlueprintRepository
- ✅ Migration files
- ✅ Integration tests

**Estimate**: 12-16 hours

#### Day 5: AgentFactory

**Files to create:**
- `packages/addons/src/Agent/AgentFactory.php`
- `packages/addons/src/Agent/Capabilities/Skills/SkillTool.php`

**Deliverables:**
- ✅ AgentFactory with blueprint reconstruction
- ✅ SkillTool wrapper (Skill → ToolInterface)
- ✅ Capability name mapping
- ✅ Factory tests

**Estimate**: 8-10 hours

### Week 2: Laravel Integration (CRITICAL)

#### Day 1-2: Service Provider

**Files to create:**
- `app/Providers/AgentServiceProvider.php`
- Tool factory registrations
- Blueprint registrations

**Deliverables:**
- ✅ Service provider with all registrations
- ✅ Core tools registered in ToolRegistry
- ✅ Skills registered as task.* tools
- ✅ Static blueprints registered
- ✅ Integration tests

**Estimate**: 10-12 hours

#### Day 3-4: Job Implementation

**Files to create:**
- `app/Jobs/ExecuteAgentJob.php`
- Database migrations for conversations
- Eloquent models

**Deliverables:**
- ✅ ExecuteAgentJob with full lifecycle
- ✅ Conversation tracking
- ✅ State persistence
- ✅ Resume mechanism
- ✅ Job tests

**Estimate**: 12-16 hours

#### Day 5: Discovery API

**Files to create:**
- API routes
- Controllers for blueprint CRUD
- Controllers for conversation management

**Deliverables:**
- ✅ List agents endpoint
- ✅ Get agent details endpoint
- ✅ Create custom agent endpoint
- ✅ Execute agent endpoint
- ✅ Resume conversation endpoint
- ✅ API tests

**Estimate**: 8-10 hours

### Week 3: Advanced Features (HIGH)

#### Tool Discovery Tools

**Files to create:**
- `packages/addons/src/Agent/Tools/ToolListTool.php`
- `packages/addons/src/Agent/Tools/ToolDescribeTool.php`

**Deliverables:**
- ✅ tool.list implementation
- ✅ tool.describe implementation
- ✅ Registered in ToolRegistry
- ✅ Tests

**Estimate**: 6-8 hours

#### UseSubagents Integration

**Files to update:**
- `packages/addons/src/Agent/Capabilities/Subagent/UseSubagents.php`

**Changes:**
- Use BlueprintRepository for discovery
- Dispatch ExecuteAgentJob for spawning
- Remove direct agent instantiation

**Estimate**: 4-6 hours

#### Blueprint Parameterization (Optional)

**Files to create:**
- `packages/addons/src/Agent/Blueprint/ParameterizedBlueprint.php`

**Deliverables:**
- ✅ Parameter interpolation
- ✅ Config merging
- ✅ Job support for parameters

**Estimate**: 4-6 hours

### Week 4: Polish & Testing (MEDIUM)

#### Comprehensive Testing

- End-to-end job execution tests
- State serialization/restoration tests
- Multi-source blueprint resolution tests
- Failure scenario tests
- Performance tests

**Estimate**: 16-20 hours

#### Documentation

- Architecture documentation
- API documentation
- Blueprint creation guide
- Deployment guide

**Estimate**: 8-10 hours

---

## Critical Design Decisions

### 1. Blueprint ID Format

**Decision**: Use simple string IDs, no prefixes

```php
✅ 'code-reviewer'
✅ 'security-analyst'
❌ 'blueprint:code-reviewer'
❌ 'static:code-reviewer'
```

**Rationale**:
- Simpler for developers
- CompositeBlueprintRepository handles source resolution transparently
- No coupling between ID and storage location

### 2. Tool Factory vs Instance Registration

**Decision**: Support both, prefer factories for serialization

```php
// ✅ Factory (preferred for jobs)
$registry->registerFactory('file.read', fn() => new ReadFileTool());

// ✅ Instance (acceptable for singleton tools)
$registry->register('file.read', new ReadFileTool());
```

**Rationale**:
- Factories enable reconstruction in worker
- Instances are fine for stateless tools
- Registry checks instance cache before calling factory

### 3. Capability Name Mapping

**Decision**: String-based capability names with factory method

```php
// In AgentBlueprint
capabilityNames: ['UseSubagents', 'UseSelfCritique']

// In AgentFactory
private function addCapability(AgentBuilder $builder, string $name, array $config): AgentBuilder
{
    return match($name) {
        'UseSubagents' => $builder->withCapability(new UseSubagents(...)),
        'UseSelfCritique' => $builder->withCapability(new UseSelfCritique(...)),
        ...
    };
}
```

**Rationale**:
- Capabilities aren't serializable (hold state/services)
- Factory pattern allows reconstruction
- Config passed for parameterization

### 4. State Serialization Format

**Decision**: Plain PHP arrays (JSON-compatible)

```php
// AgentState::toArray() returns:
[
    'messages' => [...],
    'context' => [...],
    'step' => 5,
    'metadata' => [...],
]
```

**Rationale**:
- Compatible with any storage (Redis, DB, filesystem)
- No serialization issues
- Easy to inspect/debug

### 5. Multi-Source Priority

**Decision**: Memory → Database → Filesystem

```php
CompositeBlueprintRepository::find($id)
    1. Check InMemoryBlueprintRepository (fastest)
    2. Check DatabaseBlueprintRepository (user-created)
    3. Check FilesystemBlueprintRepository (AGENT.md files)
```

**Rationale**:
- In-memory is fastest
- User-created blueprints override filesystem
- Filesystem provides backward compatibility

### 6. Job Queue Configuration

**Decision**: Use separate queue for agent jobs

```php
// config/queue.php
'queues' => [
    'default' => ...,
    'agents' => [  // Dedicated queue for agents
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'agents',
        'retry_after' => 600,  // 10 minutes
    ],
],
```

**Rationale**:
- Isolate long-running agent jobs
- Prevent blocking other jobs
- Easier to scale workers

### 7. Conversation Model vs Event Sourcing

**Decision**: Simple conversation model for MVP

```php
// Simple approach (MVP)
Conversation hasMany Messages

// Alternative: Event sourcing
ConversationEvent (agent_started, tool_called, response_generated, etc.)
```

**Rationale**:
- Event sourcing is better long-term but complex
- Start simple, migrate later if needed
- Conversation model is sufficient for resume/tracking

---

## Open Questions for Review

### Architecture Questions

1. **Blueprint versioning**: How do we handle blueprint updates?
   - Option A: Immutable - create new blueprint with version suffix
   - Option B: Mutable - update in place, version in metadata
   - Option C: Hybrid - store versions in separate table

2. **Skill execution model**: What does SkillTool actually do?
   - Option A: Inject skill content into agent context
   - Option B: Spawn subagent with skill context
   - Option C: Execute skill-specific logic (code)

3. **Tool namespace aliasing**: When to convert canonical ↔ provider alias?
   - Option A: At registration time (all tools have both names)
   - Option B: At schema generation time (convert when building tool schema)
   - Option C: At runtime (convert tool call results back)

4. **Capability configuration**: How to handle capability-specific config?
   - Current: Pass via blueprint.config
   - Alternative: Nested structure per capability
   ```php
   config: [
       'UseSubagents' => ['max_depth' => 3],
       'UseSelfCritique' => ['threshold' => 0.8],
   ]
   ```

5. **State persistence frequency**: When to save agent state?
   - Option A: After every tool call (safe, but DB-heavy)
   - Option B: After every agent step (balanced)
   - Option C: Only when job completes (risky if crash)

### Implementation Questions

6. **ToolRegistry initialization**: Where do we register tools?
   - Current: AgentServiceProvider::boot()
   - Alternative: Separate ToolServiceProvider
   - Alternative: Auto-discovery via convention

7. **Blueprint discovery caching**: Cache blueprint list?
   - Issue: Filesystem scan is slow for many AGENT.md files
   - Option A: Cache in Redis with TTL
   - Option B: Cache in database (rebuild on deploy)
   - Option C: No cache, accept slowness

8. **Job failure handling**: How aggressive to retry?
   - Current: 3 retries
   - Consider: Exponential backoff? Different retry based on error?

9. **Conversation cleanup**: When to delete old conversations?
   - Option A: Never (keep all history)
   - Option B: TTL-based (30 days)
   - Option C: User-initiated

10. **Blueprint validation**: How strict?
    - Should we validate toolNames/skillNames exist at save time?
    - Or allow references to not-yet-registered tools?

### Performance Questions

11. **Tool factory overhead**: Is factory call per tool too slow?
    - Concern: Creating tools on every job execution
    - Solution: Cache tool instances in registry?

12. **Blueprint resolution**: Is 3-way lookup too slow?
    - Concern: Checking memory → DB → filesystem every time
    - Solution: Cache resolved blueprints?

13. **Skill loading**: Load all skills at boot or lazy load?
    - Current: Lazy load (only when tool invoked)
    - Alternative: Eager load all skills into registry

### Security Questions

14. **Custom blueprint validation**: How to prevent malicious blueprints?
    - Concern: User creates blueprint with dangerous tool combinations
    - Solution: Whitelist allowed tools for custom blueprints?

15. **Tool access control**: Should blueprints specify tool permissions?
    ```php
    toolNames: [
        'file.read' => ['path' => '/allowed/path/*'],
        'file.write' => ['denied'],
    ]
    ```

16. **State encryption**: Should AgentState be encrypted in DB?
    - Concern: State might contain sensitive data
    - Solution: Encrypt state JSON before storing?

### Team Coordination Questions

17. **Migration strategy**: How to migrate existing AgentRegistry?
    - Do we deprecate AgentRegistry completely?
    - Or keep both systems running in parallel?

18. **Testing strategy**: Unit vs integration vs E2E?
    - How much coverage needed before deployment?
    - Can we test with real LLM or need mocks?

19. **Deployment order**: Which environments first?
    - Dev → Staging → Prod?
    - Feature flag for gradual rollout?

20. **Monitoring**: What metrics to track?
    - Job success/failure rates
    - Agent execution duration
    - Tool usage patterns
    - Blueprint popularity

---

## Appendices

### A. Comparison with Current System

| Aspect | Current System | New System |
|--------|---------------|------------|
| **Agent Definition** | Inline AgentBuilder | AgentBlueprint (serializable) |
| **Tool Registration** | Direct withTools() | ToolRegistry with factories |
| **Agent Storage** | Not serializable | Multiple repositories |
| **Job Compatibility** | ❌ No | ✅ Yes |
| **Discovery** | Manual | BlueprintRepository::all() |
| **Dynamic Creation** | ❌ No | ✅ Via database repository |
| **State Management** | In-memory only | Serializable, restorable |
| **Multi-source** | Filesystem only | Memory + DB + Filesystem |

### B. Migration Path

**Phase 1**: Implement new system alongside old
- Keep existing AgentRegistry
- Add BlueprintRepository system
- FilesystemBlueprintRepository wraps AgentRegistry

**Phase 2**: Gradual migration
- Convert core agents to blueprints
- Update examples to use new system
- Keep old API for compatibility

**Phase 3**: Deprecation
- Mark old AgentRegistry as deprecated
- Provide migration guide
- Set removal date (6 months)

**Phase 4**: Removal
- Remove old AgentRegistry
- Update all code to new system

### C. Performance Estimates

Based on similar systems:

| Operation | Current | New System | Impact |
|-----------|---------|------------|--------|
| Agent Build | 50ms | 80ms (+60%) | More lookups |
| Tool Invocation | 10ms | 12ms (+20%) | Registry lookup |
| State Save | N/A | 100ms | New feature |
| State Restore | N/A | 150ms | New feature |
| Blueprint List | 200ms | 50ms (-75%) | Cached metadata |

### D. Related Documentation

- **Tool Discovery**: `./2025-01-06-agent-tool-discovery/05-revised-architecture.md`
- **Progressive Disclosure**: `./2025-01-06-agent-tool-discovery/02-progressive-disclosure.md`
- **Agent Architecture**: `./2026-01-05-agent-next/00-executive-summary.md`
- **Existing AgentRegistry**: `packages/addons/src/Agent/Registry/AgentRegistry.php`
- **Existing SkillLibrary**: `packages/addons/src/Agent/Capabilities/Skills/SkillLibrary.php`

---

## Review Checklist

Before approval, please review:

- [ ] **Architecture soundness** - Does blueprint pattern make sense?
- [ ] **Serialization approach** - Is factory-based reconstruction correct?
- [ ] **Multi-source strategy** - Memory + DB + Filesystem, good priority?
- [ ] **Job implementation** - ExecuteAgentJob correct for Laravel?
- [ ] **State management** - Serialization format appropriate?
- [ ] **Performance impact** - Acceptable overhead?
- [ ] **Migration path** - Can we migrate existing system?
- [ ] **Testing strategy** - Sufficient test coverage planned?
- [ ] **Security concerns** - Any vulnerabilities?
- [ ] **API design** - Discovery endpoints make sense?
- [ ] **Implementation timeline** - 3-4 weeks realistic?
- [ ] **Open questions** - Which need answers before implementation?

---

**Next Steps After Approval:**

1. Address open questions from review
2. Create detailed task breakdown
3. Set up feature branch
4. Begin Week 1 implementation
5. Daily standups for coordination

---

## CRITICAL UPDATE: AgentSpec Already Exists!

**Date**: 2025-01-06 (same day as original document)
**Impact**: Massive simplification - reduces implementation from 3-4 weeks to ~6 hours

### The Realization

After completing this document, we discovered **AgentSpec already does everything AgentBlueprint was supposed to do!**

### What AgentSpec Already Has

```php
// packages/addons/src/Agent/Registry/AgentSpec.php

final readonly class AgentSpec
{
    public function __construct(
        public string $name,              // ✅ Unique identifier
        public string $description,       // ✅ Description
        public string $systemPrompt,      // ✅ System prompt
        public ?array $tools = null,      // ✅ Tool names (not instances!)
        public LLMConfig|string|null $model = null, // ✅ Model config
        public ?array $skills = null,     // ✅ Skill names
        public array $metadata = [],      // ✅ Metadata
    ) {}

    public function toArray(): array {    // ✅ Already serializable!
        return [
            'name' => $this->name,
            'description' => $this->description,
            'systemPrompt' => $this->systemPrompt,
            'tools' => $this->tools,
            'model' => $this->serializeModel(),
            'skills' => $this->skills,
            'metadata' => $this->metadata,
        ];
    }

    // ✅ Validation built-in
    // ✅ Tool filtering logic
    // ✅ Model resolution
}
```

**AgentSpec is already perfect for Laravel jobs!**

### What's Missing (Minor Additions Only)

```php
class AgentSpec
{
    public function __construct(
        // ... existing fields ...
        public ?array $capabilities = null,  // ← ADD: Capability names
        public array $config = [],           // ← ADD: Capability config
        // ... existing fields ...
    ) {}

    // ← ADD: Deserialization
    public static function fromArray(array $data): self {
        return new self(
            name: $data['name'],
            description: $data['description'],
            systemPrompt: $data['systemPrompt'],
            tools: $data['tools'] ?? null,
            model: self::deserializeModel($data['model'] ?? null),
            skills: $data['skills'] ?? null,
            capabilities: $data['capabilities'] ?? null,
            config: $data['config'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }
}
```

### Massively Simplified Architecture

**Replace This:**
```
AgentBlueprint (new class, ~200 lines)
  ↓
BlueprintRepository (new interface)
  ↓
InMemoryBlueprintRepository (new class, ~100 lines)
DatabaseBlueprintRepository (new class, ~100 lines)
FilesystemBlueprintRepository (new class, ~100 lines)
CompositeBlueprintRepository (new class, ~100 lines)
  ↓
AgentFactory::fromBlueprint()
```

**With This:**
```
AgentSpec (already exists, add 3 fields + fromArray)
  ↓
AgentSpecRepository (new interface, same as BlueprintRepository)
  ↓
InMemoryAgentSpecRepository (new, ~80 lines)
DatabaseAgentSpecRepository (new, ~80 lines)
FilesystemAgentSpecRepository (wraps existing AgentRegistry, ~30 lines!)
CompositeAgentSpecRepository (new, ~80 lines)
  ↓
AgentFactory::fromSpec()
```

**Key insight:** FilesystemAgentSpecRepository just wraps the **existing AgentRegistry** - no new parsing logic needed!

### Updated Implementation Estimate

**Original Plan**: 3-4 weeks
**Revised Plan**: ~6 hours

| Task | Original | Revised | Savings |
|------|----------|---------|---------|
| AgentBlueprint class | 4 hours | 10 min (add 3 fields) | -3h 50m |
| Repository implementations | 24 hours | 4 hours | -20 hours |
| AgentFactory | 8 hours | 2 hours | -6 hours |
| Testing | 16 hours | 2 hours | -14 hours |
| **Total** | **52 hours** | **8 hours** | **-44 hours (85% reduction)** |

### What This Changes in the Document

Throughout this document, mentally replace:

- `AgentBlueprint` → `AgentSpec`
- `BlueprintRepository` → `AgentSpecRepository`
- `$blueprint` → `$spec`
- `fromBlueprint()` → `fromSpec()`

The **architecture patterns remain valid**:
- ✅ Multi-source repository (memory/DB/filesystem)
- ✅ Factory-based reconstruction
- ✅ Serialization via toArray()/fromArray()
- ✅ Laravel job integration
- ✅ Discovery API

The **implementation is just simpler** because we're enhancing an existing class instead of creating a new one.

### Updated Critical Path

#### Phase 1: Enhance AgentSpec (1 hour)
1. Add `capabilities` field
2. Add `config` field
3. Add `fromArray()` method
4. Update `toArray()` to include new fields
5. Write tests

#### Phase 2: Repository Pattern (3 hours)
1. Create `AgentSpecRepository` interface
2. Implement `InMemoryAgentSpecRepository`
3. Implement `DatabaseAgentSpecRepository`
4. Implement `FilesystemAgentSpecRepository` (wraps existing `AgentRegistry`)
5. Implement `CompositeAgentSpecRepository`
6. Write tests

#### Phase 3: Factory & Jobs (2 hours)
1. Update `AgentFactory::fromSpec()`
2. Implement `ExecuteAgentJob`
3. Add service provider registrations
4. Write tests

#### Phase 4: Discovery API (2 hours)
1. Create API endpoints
2. Test end-to-end flow

**Total: ~8 hours instead of 3-4 weeks**

### Why This Wasn't Obvious Initially

The mistake happened because:

1. ❌ **Didn't read existing code first** - Assumed we needed a new abstraction
2. ❌ **Name confusion** - "Spec" sounds like it's for parsing, "Blueprint" sounds like it's for runtime
3. ❌ **Over-engineering** - Created new abstraction without checking if existing one works

**Lesson**: Always check existing codebase before designing new abstractions.

### Action Items for Team Review

1. **Validate this simplification** - Does AgentSpec really cover all needs?
2. **Check AgentRegistry integration** - Can FilesystemAgentSpecRepository just wrap it?
3. **Verify serialization** - Test AgentSpec::toArray()/fromArray() with LLMConfig
4. **Review capability mapping** - Is array of strings sufficient?
5. **Update timeline** - Can we actually implement in ~8 hours?

### Recommendation

**Do NOT implement the full plan as written above.** Instead:

1. Review this update section with team
2. Validate that AgentSpec enhancement is sufficient
3. Create simplified implementation plan (8 hours, not 3 weeks)
4. Implement minimal changes to existing AgentSpec
5. Build repository pattern on top of enhanced AgentSpec

This saves ~44 hours of implementation time and avoids creating duplicate abstractions.

---

**Document Version**: 1.1
**Last Updated**: 2025-01-06 (updated same day with critical simplification)
**Authors**: Claude Code, Senior Dev Team
**Status**: Awaiting Review (with major simplification discovered)
