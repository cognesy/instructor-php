# DX Showcase: Clean, Beautiful Agent Workflows

**No nested IFs. Pure flow. Expressive intent.**

## Example 1: Agent Session Start (Clean Flow)

```php
<?php

use App\Services\Beads\Facades\Beads;

// Agent identifies itself
Beads::as('agent-alpha');

// Recover context and determine action
$context = Beads::sessionContext();
$action = $context->recommendedAction();

// Clean dispatch based on recommended action
match($action) {
    'respond_to_mentions' => handleMentions($context->mentions),
    'resume_current_task' => resumeTask($context->currentTask),
    'resume_active_work' => pickActiveTask($context->activeTasks),
    'find_new_task' => claimNextTask(),
    default => idle(),
};

// Handler functions (clean, focused, single-purpose)

function handleMentions(TaskCollection $mentions): void
{
    foreach ($mentions as $task) {
        $task->comment('Reviewing your mention...')
             ->start();

        $comments = $task->comments()->unread();
        respondToComments($task, $comments);

        $task->complete('Addressed your feedback');
    }
}

function resumeTask(Task $task): void
{
    $task->comment('Resuming work after restart');

    match($task->status) {
        'in_progress' => continueWork($task),
        'open' => $task->start()->then(fn($t) => continueWork($t)),
        default => $task->refresh()->then(fn($t) => resumeTask($t)),
    };
}

function pickActiveTask(TaskCollection $tasks): void
{
    $task = $tasks->highPriority()->first()
         ?? $tasks->first();

    resumeTask($task);
}

function claimNextTask(): void
{
    $task = Beads::nextTask();

    match(true) {
        $task === null => echo "No tasks available\n",
        $task->isBlocked() => echo "Next task is blocked\n",
        default => startWork($task),
    };
}

function startWork(Task $task): void
{
    $task->claim()
         ->comment('Starting work')
         ->log('Claimed task at ' . now());

    // Analyze and break down
    $complexity = analyzeComplexity($task);

    match($complexity) {
        'simple' => implementDirectly($task),
        'medium' => breakIntoSubtasks($task),
        'complex' => createEpicWithPhases($task),
    };
}
```

## Example 2: Smart Task Breakdown (No Nested IFs)

```php
<?php

use App\Services\Beads\Facades\Beads;

Beads::as('agent-architect');

$task = Beads::find('bd-feature-123');
$task->claim()->comment('Analyzing feature requirements...');

// Determine task type and handle accordingly
$taskType = classifyTask($task);

match($taskType) {
    'quick_fix' => handleQuickFix($task),
    'standard_feature' => handleStandardFeature($task),
    'complex_system' => handleComplexSystem($task),
    'research_spike' => handleResearchSpike($task),
};

// Clean handlers

function handleQuickFix(Task $task): void
{
    $task->start()
         ->comment('Quick fix - implementing directly')
         ->setPriority(0);

    implementFix($task);

    $task->complete('Fixed in single commit');
}

function handleStandardFeature(Task $task): void
{
    $task->comment('Breaking into standard phases');

    $phases = [
        'Design data model',
        'Implement backend logic',
        'Create frontend components',
        'Write tests',
    ];

    Beads::epic($task->title)
        ->sequentialTasks($phases)
        ->create();

    $task->complete('Broken into subtasks');
}

function handleComplexSystem(Task $task): void
{
    $task->comment('Complex system - creating epic with parallel streams');

    $epic = Beads::epic($task->title)
        ->description('Multi-phase implementation')
        ->parallelTasks([
            '[backend] Design and implement API',
            '[frontend] Build UI components',
            '[infra] Set up infrastructure',
        ])
        ->subtask('[integration] End-to-end testing', fn($b) =>
            $b->priority(1)
              ->description('Depends on all parallel streams')
        )
        ->subtask('[docs] Write documentation', fn($b) =>
            $b->priority(2)
        )
        ->create();

    $task->comment("Created epic: {$epic->task->id}")
         ->complete('Epic ready for execution');
}

function handleResearchSpike(Task $task): void
{
    $task->comment('Research spike - time-boxed exploration')
         ->setPriority(1)
         ->addLabel('research')
         ->addLabel('time-boxed')
         ->start();

    conductResearch($task);

    $task->complete('Research findings documented in comments');
}
```

## Example 3: Multi-Agent Coordination (Pure Flow)

```php
<?php

use App\Services\Beads\Facades\Beads;

// Orchestrator creates work
Beads::as('orchestrator');

$epic = Beads::epic('[epic] Build payment system')
    ->parallelTasks([
        '[backend] Stripe integration',
        '[frontend] Payment form',
        '[security] PCI compliance audit',
    ])
    ->subtask('[integration] End-to-end tests')
    ->subtask('[deployment] Production rollout')
    ->create();

echo "Epic created: {$epic->task->id}\n";
echo "Subtasks available for agents\n";

// Agent pool claims work
$agents = ['agent-backend', 'agent-frontend', 'agent-security'];

foreach ($agents as $agentId) {
    Beads::as($agentId);

    $task = Beads::available()->first();

    match(true) {
        $task === null =>
            echo "{$agentId}: No work available\n",
        $task->isBlocked() =>
            echo "{$agentId}: Next task blocked\n",
        default =>
            $task->claim()
                 ->comment("Claimed by {$agentId}")
                 ->start(),
    };
}

// Agents work independently (clean dispatch)
Beads::as('agent-backend');
$myTask = Beads::mine()->inProgress()->first();

$progress = checkProgress($myTask);

match($progress) {
    'blocked' => $myTask->block('Waiting on API keys'),
    'needs_help' => $myTask->mention('orchestrator', 'Need guidance on error handling'),
    'discovering_work' => createDiscoveredTask($myTask),
    'complete' => $myTask->complete('Stripe integration done'),
    default => continueWork($myTask),
};
```

## Example 4: Error Recovery (No Nested IFs)

```php
<?php

use App\Services\Beads\Facades\Beads;

Beads::as('agent-worker');

$task = Beads::find('bd-task-789');
$task->start();

try {
    $result = performWork($task);

    match($result->status) {
        'success' => handleSuccess($task, $result),
        'partial' => handlePartial($task, $result),
        'blocked' => handleBlocked($task, $result),
        'error' => handleError($task, $result),
    };

} catch (Exception $e) {
    handleException($task, $e);
}

// Clean handlers

function handleSuccess(Task $task, $result): void
{
    $task->comment("Completed successfully: {$result->summary}")
         ->complete($result->details);
}

function handlePartial(Task $task, $result): void
{
    $task->comment("Partial completion: {$result->completed}")
         ->comment("Remaining work: {$result->remaining}");

    $remainingTask = $task->createSubtask("Complete: {$result->remaining}")
        ->assignToMe()
        ->create();

    $task->complete('Partial work done, remaining in subtask');
}

function handleBlocked(Task $task, $result): void
{
    $blocker = Beads::task("[blocker] {$result->blocker}")
        ->type('bug')
        ->priority(0)
        ->discoveredFrom($task)
        ->assignToMe()
        ->createAndClaim();

    $task->dependsOn($blocker)
         ->block("Blocked by: {$blocker->id}");

    // Focus on blocker now
    handleBlocker($blocker);
}

function handleError(Task $task, $result): void
{
    $task->comment("ERROR: {$result->error}")
         ->comment("Stack trace:\n{$result->trace}")
         ->mention('human', 'Hit error, need guidance')
         ->block('awaiting_human_input');
}

function handleException(Task $task, Exception $e): void
{
    $task->comment("EXCEPTION: {$e->getMessage()}")
         ->comment("File: {$e->getFile()}:{$e->getLine()}")
         ->abandon('Exception occurred, needs investigation');
}
```

## Example 5: Real-World Feature Implementation (Beautiful Flow)

```php
<?php

use App\Services\Beads\Facades\Beads;

Beads::as('agent-fullstack');

// Get next task
$task = Beads::nextTask() ?? exit("No work available\n");

$task->claim()
     ->comment('Analyzing feature requirements...');

// Understand the task
$requirements = analyzeRequirements($task);
$approach = determineApproach($requirements);

// Execute based on approach
match($approach) {
    'simple_crud' => implementSimpleCrud($task, $requirements),
    'complex_feature' => implementComplexFeature($task, $requirements),
    'api_integration' => implementApiIntegration($task, $requirements),
    'ui_component' => implementUiComponent($task, $requirements),
};

// Implementation handlers

function implementSimpleCrud(Task $task, $requirements): void
{
    $task->start()->comment('Simple CRUD - implementing directly');

    $steps = [
        'model' => fn() => createModel($requirements),
        'migration' => fn() => createMigration($requirements),
        'controller' => fn() => createController($requirements),
        'views' => fn() => createViews($requirements),
        'tests' => fn() => createTests($requirements),
    ];

    foreach ($steps as $step => $action) {
        $task->log("Starting: {$step}");

        $result = $action();

        match($result->status) {
            'success' => $task->log("Completed: {$step}"),
            'error' => throw new Exception("Failed at {$step}: {$result->error}"),
        };
    }

    $task->complete('CRUD implementation complete with tests');
}

function implementComplexFeature(Task $task, $requirements): void
{
    $task->comment('Complex feature - breaking into phases');

    // Create phase 1: Backend
    $backend = $task->createSubtask('[backend] API implementation')
        ->priority(1)
        ->assignToMe()
        ->withComment('REST endpoints for ' . $requirements->entity)
        ->createAndClaim();

    implementBackend($backend, $requirements);
    $backend->complete('API endpoints implemented');

    // Create phase 2: Frontend (unblocked by backend)
    $frontend = $task->createSubtask('[frontend] UI components')
        ->priority(1)
        ->assignToMe()
        ->dependsOn($backend)
        ->withComment('React components for ' . $requirements->entity)
        ->createAndClaim();

    implementFrontend($frontend, $requirements);
    $frontend->complete('UI components implemented');

    // Create phase 3: Integration
    $integration = $task->createSubtask('[integration] Connect frontend to API')
        ->priority(1)
        ->assignToMe()
        ->dependsOn($frontend)
        ->createAndClaim();

    implementIntegration($integration, $requirements);
    $integration->complete('Integration complete');

    // Complete parent
    $task->complete('Complex feature complete: all phases done');
}

function implementApiIntegration(Task $task, $requirements): void
{
    $task->start()->comment('Integrating with external API');

    // Check credentials
    $credentials = checkCredentials($requirements->apiName);

    match($credentials->status) {
        'missing' => handleMissingCredentials($task, $requirements),
        'invalid' => handleInvalidCredentials($task, $requirements),
        'valid' => proceedWithIntegration($task, $requirements, $credentials),
    };
}

function handleMissingCredentials(Task $task, $requirements): void
{
    $task->mention('human',
        "Missing API credentials for {$requirements->apiName}. " .
        "Please provide: API key, secret, endpoint URL"
    )
    ->block('awaiting_credentials');
}

function proceedWithIntegration(Task $task, $requirements, $credentials): void
{
    $steps = [
        'client' => fn() => createApiClient($requirements, $credentials),
        'service' => fn() => createServiceWrapper($requirements),
        'tests' => fn() => createIntegrationTests($requirements),
        'docs' => fn() => documentApiUsage($requirements),
    ];

    foreach ($steps as $step => $action) {
        $task->log("Implementing: {$step}");

        try {
            $action();
            $task->log("✓ {$step} complete");
        } catch (Exception $e) {
            $task->comment("Failed at {$step}: {$e->getMessage()}")
                 ->abandon("Integration failed, needs debugging");
            return;
        }
    }

    $task->complete('API integration complete and tested');
}

function implementUiComponent(Task $task, $requirements): void
{
    $task->start()->comment('Building UI component');

    $complexity = assessComponentComplexity($requirements);

    match($complexity) {
        'simple' => buildSimpleComponent($task, $requirements),
        'composite' => buildCompositeComponent($task, $requirements),
        'complex' => buildComplexComponent($task, $requirements),
    };
}

function buildSimpleComponent(Task $task, $requirements): void
{
    $task->comment('Simple component - single file');

    createComponentFile($requirements);
    createComponentTests($requirements);
    createStorybook($requirements);

    $task->complete('Component built: ' . $requirements->componentName);
}

function buildCompositeComponent(Task $task, $requirements): void
{
    $task->comment('Composite component - multiple parts');

    $parts = identifyComponentParts($requirements);

    foreach ($parts as $part) {
        $subtask = $task->createSubtask("[component] Build {$part->name}")
            ->assignToMe()
            ->createAndClaim();

        createComponentPart($subtask, $part);
        $subtask->complete("Part complete: {$part->name}");
    }

    $task->complete('Composite component complete: all parts done');
}
```

## Example 6: The "Wow" Moment (Minimal Code, Maximum Power)

```php
<?php

use App\Services\Beads\Facades\Beads;

// ONE LINE: Agent session start with full context recovery
match(Beads::as('agent-alpha')->sessionContext()->recommendedAction()) {
    'respond_to_mentions' => Beads::mentions()->each->start(),
    'resume_current_task' => Beads::sessionContext()->currentTask->start(),
    'find_new_task' => Beads::nextTask()?->claim()->start(),
    default => null,
};

// ONE FLOW: Claim task, break down, implement, complete
Beads::nextTask()
    ?->claim()
    ->comment('Starting implementation')
    ->createSubtask('Write tests')->assignToMe()->create()
    ->createSubtask('Implement feature')->assignToMe()->create()
    ->createSubtask('Update docs')->assignToMe()->create()
    ->complete('Broken into subtasks');

// ONE EXPRESSION: Handle any task outcome
match(implementFeature($task)) {
    'success' => $task->complete('Done'),
    'blocked' => $task->block('Waiting on dependency'),
    'error' => $task->abandon('Failed'),
    'partial' => $task->comment('Progress made')->log('50% complete'),
};

// ONE PIPELINE: Find work → claim → execute → complete
Beads::available()
    ->highPriority()
    ->first()
    ?->claim()
    ->start()
    ->then(fn($t) => executeWork($t))
    ->complete('All done');
```

## Why This API Is Irresistible

### ✨ No Noise
```php
// ❌ Traditional API (noise everywhere)
$issue = $client->getIssue($id);
if ($issue !== null) {
    if ($issue->getStatus() === 'open') {
        if ($issue->getAssignee() === null) {
            $updateRequest = new UpdateIssueRequest();
            $updateRequest->setAssignee($agentId);
            $updateRequest->setStatus('in_progress');
            $issue = $client->updateIssue($id, $updateRequest);
            if ($issue !== null) {
                $client->addComment($id, 'Starting work');
            }
        }
    }
}

// ✅ Our API (pure signal)
Beads::find($id)->claim()->comment('Starting work');
```

### 🎯 Expressive Intent
```php
// What you mean              // What you write
"Get next task"           => Beads::nextTask()
"Claim and start"         => $task->claim()->start()
"Break into subtasks"     => $task->createSubtask('...')
"Hand off to another"     => $task->handoff('agent-2')
"Block until fix"         => $task->block('Waiting on bug')
"Complete with summary"   => $task->complete('Done!')
```

### 🔄 Zero Ceremony
```php
// Start working (3 words)
Beads::nextTask()?->claim();

// Resume session (1 line)
match(Beads::sessionContext()->recommendedAction()) {
    'resume_current_task' => Beads::sessionContext()->currentTask->start(),
    default => Beads::nextTask()?->claim(),
};

// Create epic with subtasks (fluent)
Beads::epic('Feature X')
    ->parallelTasks(['Backend', 'Frontend', 'Tests'])
    ->create();
```

### 🎨 Beautiful Composition
```php
// Filter, sort, act (reads like English)
Beads::mine()
    ->inProgress()
    ->highPriority()
    ->withLabel('urgent')
    ->sortBy('priority')
    ->each(fn($t) => $t->comment('Reviewing status'));

// Conditional chaining (no IFs)
$task->claim()
     ->start()
     ->when($needsSubtasks, fn($t) => $t->createSubtask('Tests'))
     ->comment('Working on it')
     ->complete('Done');
```

## The Bottom Line

**This API makes you WANT to use bd because it feels like thinking, not coding.**

No nested IFs. No ceremony. No noise. Just pure, expressive intent.

```php
// This is the entire agent loop
Beads::as('agent')->forever(function() {
    match(Beads::sessionContext()->recommendedAction()) {
        'respond_to_mentions' => handleMentions(),
        'resume_current_task' => continueWork(),
        'find_new_task' => claimNext(),
        default => idle(),
    };
});
```

**Want to use this API now?** ✨
