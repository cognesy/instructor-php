# Deploying Instructor Agents in Laravel Applications

This document covers practical patterns for embedding AI agents in Laravel applications, including job execution, status communication, lifecycle management, and scaling.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Job-Based Execution](#job-based-execution)
3. [Status Communication & Logging](#status-communication--logging)
4. [Agent Lifecycle Management](#agent-lifecycle-management)
5. [Scaling & Parallel Execution](#scaling--parallel-execution)
6. [Event-Driven Awakening](#event-driven-awakening)
7. [Long-Running Jobs](#long-running-jobs)
8. [Complete Implementation Example](#complete-implementation-example)

---

## Architecture Overview

### Core Components

```
┌─────────────────────────────────────────────────────────────────┐
│                      Laravel Application                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │   HTTP/API   │───▶│ AgentService │───▶│  Queue Dispatch  │   │
│  └──────────────┘    └──────────────┘    └────────┬─────────┘   │
│                                                    │             │
│  ┌──────────────┐    ┌──────────────┐             │             │
│  │   Admin UI   │◀───│   Database   │◀────────────┤             │
│  └──────────────┘    │   (state,    │             │             │
│         ▲            │    logs)     │             ▼             │
│         │            └──────────────┘    ┌──────────────────┐   │
│         │                                │  Queue Workers   │   │
│  ┌──────┴───────┐                        │  (Horizon/etc)   │   │
│  │  WebSocket   │◀───────────────────────┤                  │   │
│  │  Broadcasting│                        │  ┌────────────┐  │   │
│  └──────────────┘                        │  │   Agent    │  │   │
│                                          │  │  Executor  │  │   │
│                                          │  └────────────┘  │   │
│                                          └──────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Database Schema

```php
// Migration: create_agent_executions_table.php
Schema::create('agent_executions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('agent_type');
    $table->string('status')->default('pending'); // pending, running, paused, completed, failed, cancelled
    $table->json('input')->nullable();
    $table->json('output')->nullable();
    $table->json('state_snapshot')->nullable(); // Serialized AgentState for resume
    $table->json('metadata')->nullable();
    $table->integer('step_count')->default(0);
    $table->integer('token_usage')->default(0);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('paused_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index(['status', 'created_at']);
});

Schema::create('agent_logs', function (Blueprint $table) {
    $table->id();
    $table->uuid('execution_id');
    $table->string('level'); // debug, info, warning, error
    $table->string('event_type'); // step_started, tool_called, step_completed, etc.
    $table->text('message');
    $table->json('context')->nullable();
    $table->timestamps();

    $table->foreign('execution_id')
          ->references('id')
          ->on('agent_executions')
          ->cascadeOnDelete();
    $table->index(['execution_id', 'created_at']);
});

Schema::create('agent_signals', function (Blueprint $table) {
    $table->id();
    $table->uuid('execution_id');
    $table->string('signal_type'); // resume, pause, cancel, input
    $table->json('payload')->nullable();
    $table->boolean('processed')->default(false);
    $table->timestamps();

    $table->foreign('execution_id')
          ->references('id')
          ->on('agent_executions')
          ->cascadeOnDelete();
    $table->index(['execution_id', 'processed']);
});
```

---

## Job-Based Execution

### Basic Agent Job

```php
<?php

namespace App\Jobs;

use App\Models\AgentExecution;
use App\Services\AgentExecutionService;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max
    public int $tries = 1;     // Don't auto-retry - agent handles retries internally
    public int $maxExceptions = 1;

    public function __construct(
        public string $executionId,
    ) {}

    public function handle(AgentExecutionService $service): void
    {
        $execution = AgentExecution::findOrFail($this->executionId);

        // Check if cancelled before starting
        if ($execution->status === 'cancelled') {
            return;
        }

        $service->run($execution);
    }

    public function failed(\Throwable $exception): void
    {
        $execution = AgentExecution::find($this->executionId);
        if ($execution) {
            $execution->update([
                'status' => 'failed',
                'metadata->error' => $exception->getMessage(),
            ]);
        }

        Log::error('Agent execution failed', [
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Agent Execution Service

```php
<?php

namespace App\Services;

use App\Events\AgentStepCompleted;
use App\Events\AgentStatusChanged;
use App\Models\AgentExecution;
use App\Models\AgentLog;
use App\Models\AgentSignal;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Events\AgentStepCompleted as AgentStepCompletedEvent;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Events\EventBus;
use Cognesy\Messages\Messages;
use Illuminate\Support\Facades\Cache;

class AgentExecutionService
{
    private const SIGNAL_CHECK_INTERVAL = 5; // Check signals every N steps

    public function run(AgentExecution $execution): void
    {
        $this->updateStatus($execution, 'running');

        try {
            $agent = $this->buildAgent($execution);
            $state = $this->initializeState($execution);

            $stepNumber = 0;
            foreach ($agent->iterator($state) as $currentState) {
                $stepNumber++;

                // Log step completion
                $this->logStep($execution, $currentState, $stepNumber);

                // Broadcast progress
                broadcast(new AgentStepCompleted($execution, $stepNumber, $currentState));

                // Check for control signals periodically
                if ($stepNumber % self::SIGNAL_CHECK_INTERVAL === 0) {
                    $signal = $this->checkSignals($execution);

                    if ($signal === 'pause') {
                        $this->pauseExecution($execution, $currentState);
                        return;
                    }

                    if ($signal === 'cancel') {
                        $this->cancelExecution($execution);
                        return;
                    }
                }

                // Update progress in database
                $execution->update([
                    'step_count' => $stepNumber,
                    'token_usage' => $currentState->usage()->total(),
                ]);

                $state = $currentState;
            }

            // Execution completed successfully
            $this->completeExecution($execution, $state);

        } catch (\Throwable $e) {
            $this->failExecution($execution, $e);
            throw $e;
        }
    }

    public function resume(AgentExecution $execution): void
    {
        if ($execution->status !== 'paused') {
            throw new \InvalidArgumentException('Can only resume paused executions');
        }

        // Restore state from snapshot
        $stateData = $execution->state_snapshot;
        if (!$stateData) {
            throw new \RuntimeException('No state snapshot available for resume');
        }

        // Re-dispatch as new job
        $execution->update(['status' => 'pending']);
        ExecuteAgentJob::dispatch($execution->id);
    }

    private function buildAgent(AgentExecution $execution): Agent
    {
        $events = new EventBus();

        // Wire up event logging
        $events->onEvent(AgentStepCompletedEvent::class, function ($event) use ($execution) {
            $this->logEvent($execution, 'step_completed', $event->payload());
        });

        $events->onEvent(ToolCallCompleted::class, function ($event) use ($execution) {
            $this->logEvent($execution, 'tool_completed', $event->payload());
        });

        // Build agent based on type
        return match ($execution->agent_type) {
            'code-assistant' => $this->buildCodeAssistant($events),
            'research' => $this->buildResearchAgent($events),
            default => throw new \InvalidArgumentException("Unknown agent type: {$execution->agent_type}"),
        };
    }

    private function initializeState(AgentExecution $execution): AgentState
    {
        // If resuming, restore from snapshot
        if ($execution->state_snapshot) {
            return $this->deserializeState($execution->state_snapshot);
        }

        // Otherwise, create fresh state from input
        return AgentState::empty()->withMessages(
            Messages::fromString($execution->input['prompt'] ?? '')
        );
    }

    private function checkSignals(AgentExecution $execution): ?string
    {
        $signal = AgentSignal::where('execution_id', $execution->id)
            ->where('processed', false)
            ->orderBy('created_at')
            ->first();

        if ($signal) {
            $signal->update(['processed' => true]);
            return $signal->signal_type;
        }

        return null;
    }

    private function pauseExecution(AgentExecution $execution, AgentState $state): void
    {
        $execution->update([
            'status' => 'paused',
            'paused_at' => now(),
            'state_snapshot' => $this->serializeState($state),
        ]);

        $this->logEvent($execution, 'paused', ['step' => $execution->step_count]);
        broadcast(new AgentStatusChanged($execution));
    }

    private function cancelExecution(AgentExecution $execution): void
    {
        $execution->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        $this->logEvent($execution, 'cancelled', []);
        broadcast(new AgentStatusChanged($execution));
    }

    private function completeExecution(AgentExecution $execution, AgentState $state): void
    {
        $output = $state->currentStep()?->outputMessages()->toString();

        $execution->update([
            'status' => 'completed',
            'completed_at' => now(),
            'output' => ['response' => $output],
            'token_usage' => $state->usage()->total(),
        ]);

        $this->logEvent($execution, 'completed', [
            'steps' => $execution->step_count,
            'tokens' => $state->usage()->total(),
        ]);

        broadcast(new AgentStatusChanged($execution));
    }

    private function failExecution(AgentExecution $execution, \Throwable $e): void
    {
        $execution->update([
            'status' => 'failed',
            'completed_at' => now(),
            'metadata->error' => $e->getMessage(),
            'metadata->trace' => $e->getTraceAsString(),
        ]);

        $this->logEvent($execution, 'failed', [
            'error' => $e->getMessage(),
        ]);

        broadcast(new AgentStatusChanged($execution));
    }

    private function logStep(AgentExecution $execution, AgentState $state, int $stepNumber): void
    {
        $step = $state->currentStep();

        AgentLog::create([
            'execution_id' => $execution->id,
            'level' => 'info',
            'event_type' => 'step_completed',
            'message' => "Step {$stepNumber} completed",
            'context' => [
                'step_type' => $step?->stepType()->value,
                'has_tool_calls' => $step?->hasToolCalls(),
                'tool_names' => $step?->toolCalls()->names() ?? [],
                'tokens_used' => $step?->usage()->total(),
            ],
        ]);
    }

    private function logEvent(AgentExecution $execution, string $eventType, array $context): void
    {
        AgentLog::create([
            'execution_id' => $execution->id,
            'level' => 'info',
            'event_type' => $eventType,
            'message' => ucfirst(str_replace('_', ' ', $eventType)),
            'context' => $context,
        ]);
    }

    private function serializeState(AgentState $state): array
    {
        // Serialize state for database storage
        return [
            'messages' => $state->messages()->toArray(),
            'metadata' => $state->metadata()->toArray(),
            'step_count' => $state->stepCount(),
            'usage' => [
                'input' => $state->usage()->input(),
                'output' => $state->usage()->output(),
            ],
        ];
    }

    private function deserializeState(array $data): AgentState
    {
        // Restore state from serialized data
        return AgentState::empty()
            ->withMessages(Messages::fromArray($data['messages'] ?? []))
            ->withMetadata($data['metadata'] ?? []);
    }

    private function buildCodeAssistant(EventBus $events): Agent
    {
        return AgentBuilder::base()
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\Bash\UseBash())
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\File\UseFileTools('/var/www/workspace'))
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning())
            ->withMaxSteps(50)
            ->withTimeout(300)
            ->withEvents($events)
            ->build();
    }

    private function buildResearchAgent(EventBus $events): Agent
    {
        return AgentBuilder::base()
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\File\UseFileTools('/var/www/workspace'))
            ->withMaxSteps(100)
            ->withTimeout(600)
            ->withEvents($events)
            ->build();
    }
}
```

---

## Status Communication & Logging

### Broadcasting Events

```php
<?php

namespace App\Events;

use App\Models\AgentExecution;
use Cognesy\Addons\Agent\Data\AgentState;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStepCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentExecution $execution,
        public int $stepNumber,
        public AgentState $state,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->execution->id),
            new PrivateChannel('user.' . $this->execution->user_id . '.agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'step.completed';
    }

    public function broadcastWith(): array
    {
        $step = $this->state->currentStep();

        return [
            'execution_id' => $this->execution->id,
            'step_number' => $this->stepNumber,
            'step_type' => $step?->stepType()->value,
            'has_tool_calls' => $step?->hasToolCalls(),
            'tool_names' => $step?->toolCalls()->names() ?? [],
            'tokens_used' => $this->state->usage()->total(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

class AgentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentExecution $execution,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->execution->id),
            new PrivateChannel('user.' . $this->execution->user_id . '.agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'status' => $this->execution->status,
            'step_count' => $this->execution->step_count,
            'token_usage' => $this->execution->token_usage,
            'completed_at' => $this->execution->completed_at?->toIso8601String(),
        ];
    }
}
```

### Admin Log Viewer API

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentExecution;
use App\Models\AgentLog;
use Illuminate\Http\Request;

class AgentLogsController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentExecution::with(['user:id,name,email'])
            ->withCount('logs')
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return $query->paginate(50);
    }

    public function show(AgentExecution $execution)
    {
        return $execution->load(['user:id,name,email', 'logs' => function ($q) {
            $q->latest()->limit(500);
        }]);
    }

    public function logs(AgentExecution $execution, Request $request)
    {
        $query = $execution->logs()->latest();

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Support real-time log streaming via cursor
        if ($request->has('after_id')) {
            $query->where('id', '>', $request->after_id);
        }

        return $query->paginate(100);
    }

    public function stream(AgentExecution $execution)
    {
        // Server-Sent Events for real-time log streaming
        return response()->stream(function () use ($execution) {
            $lastId = 0;

            while (true) {
                $logs = AgentLog::where('execution_id', $execution->id)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->get();

                foreach ($logs as $log) {
                    echo "data: " . json_encode($log->toArray()) . "\n\n";
                    ob_flush();
                    flush();
                    $lastId = $log->id;
                }

                // Check if execution is finished
                $execution->refresh();
                if (in_array($execution->status, ['completed', 'failed', 'cancelled'])) {
                    echo "event: finished\n";
                    echo "data: {\"status\": \"{$execution->status}\"}\n\n";
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

### Structured Logging Trait

```php
<?php

namespace App\Traits;

use App\Models\AgentLog;

trait LogsAgentActivity
{
    protected function logDebug(string $message, array $context = []): void
    {
        $this->createLog('debug', $message, $context);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        $this->createLog('info', $message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        $this->createLog('warning', $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->createLog('error', $message, $context);
    }

    private function createLog(string $level, string $message, array $context): void
    {
        if (!isset($this->execution)) {
            return;
        }

        AgentLog::create([
            'execution_id' => $this->execution->id,
            'level' => $level,
            'event_type' => $context['event_type'] ?? 'general',
            'message' => $message,
            'context' => $context,
        ]);
    }
}
```

---

## Agent Lifecycle Management

### Agent Manager Service

```php
<?php

namespace App\Services;

use App\Jobs\ExecuteAgentJob;
use App\Models\AgentExecution;
use App\Models\AgentSignal;
use Illuminate\Support\Str;

class AgentManager
{
    public function __construct(
        private AgentExecutionService $executionService,
    ) {}

    /**
     * Start a new agent execution.
     */
    public function start(int $userId, string $agentType, array $input, array $options = []): AgentExecution
    {
        $execution = AgentExecution::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'agent_type' => $agentType,
            'status' => 'pending',
            'input' => $input,
            'metadata' => $options['metadata'] ?? [],
        ]);

        // Dispatch to appropriate queue
        $queue = $options['queue'] ?? $this->getQueueForAgentType($agentType);
        ExecuteAgentJob::dispatch($execution->id)->onQueue($queue);

        return $execution;
    }

    /**
     * Pause a running agent (will suspend at next checkpoint).
     */
    public function pause(AgentExecution $execution): void
    {
        if ($execution->status !== 'running') {
            throw new \InvalidArgumentException('Can only pause running executions');
        }

        AgentSignal::create([
            'execution_id' => $execution->id,
            'signal_type' => 'pause',
        ]);
    }

    /**
     * Resume a paused agent.
     */
    public function resume(AgentExecution $execution): void
    {
        $this->executionService->resume($execution);
    }

    /**
     * Cancel/kill an agent execution.
     */
    public function cancel(AgentExecution $execution): void
    {
        if (in_array($execution->status, ['completed', 'failed', 'cancelled'])) {
            return; // Already finished
        }

        if ($execution->status === 'pending') {
            // Not started yet - just update status
            $execution->update(['status' => 'cancelled', 'completed_at' => now()]);
            return;
        }

        // Send cancel signal for running agent
        AgentSignal::create([
            'execution_id' => $execution->id,
            'signal_type' => 'cancel',
        ]);
    }

    /**
     * Force kill - immediately terminate without graceful shutdown.
     */
    public function forceKill(AgentExecution $execution): void
    {
        // Update status immediately
        $execution->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'metadata->force_killed' => true,
        ]);

        // The job will fail on next database check
    }

    /**
     * Send input to a waiting agent.
     */
    public function sendInput(AgentExecution $execution, array $input): void
    {
        if ($execution->status !== 'awaiting_input') {
            throw new \InvalidArgumentException('Agent is not awaiting input');
        }

        AgentSignal::create([
            'execution_id' => $execution->id,
            'signal_type' => 'input',
            'payload' => $input,
        ]);

        // Resume execution
        $this->resume($execution);
    }

    private function getQueueForAgentType(string $agentType): string
    {
        return match ($agentType) {
            'research' => 'agents-long',      // Long-running queue
            'code-assistant' => 'agents-default',
            default => 'agents-default',
        };
    }
}
```

### API Controller

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartAgentRequest;
use App\Models\AgentExecution;
use App\Services\AgentManager;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(
        private AgentManager $manager,
    ) {}

    public function index(Request $request)
    {
        return $request->user()
            ->agentExecutions()
            ->latest()
            ->paginate(20);
    }

    public function store(StartAgentRequest $request)
    {
        $execution = $this->manager->start(
            userId: $request->user()->id,
            agentType: $request->agent_type,
            input: $request->input,
            options: $request->only(['metadata', 'queue']),
        );

        return response()->json($execution, 201);
    }

    public function show(AgentExecution $execution)
    {
        $this->authorize('view', $execution);
        return $execution->load('logs');
    }

    public function pause(AgentExecution $execution)
    {
        $this->authorize('update', $execution);
        $this->manager->pause($execution);

        return response()->json(['message' => 'Pause signal sent']);
    }

    public function resume(AgentExecution $execution)
    {
        $this->authorize('update', $execution);
        $this->manager->resume($execution);

        return response()->json(['message' => 'Execution resumed']);
    }

    public function cancel(AgentExecution $execution)
    {
        $this->authorize('update', $execution);
        $this->manager->cancel($execution);

        return response()->json(['message' => 'Cancel signal sent']);
    }

    public function forceKill(AgentExecution $execution)
    {
        $this->authorize('forceKill', $execution);
        $this->manager->forceKill($execution);

        return response()->json(['message' => 'Execution force killed']);
    }

    public function sendInput(Request $request, AgentExecution $execution)
    {
        $this->authorize('update', $execution);

        $request->validate(['input' => 'required|array']);
        $this->manager->sendInput($execution, $request->input);

        return response()->json(['message' => 'Input sent']);
    }
}
```

---

## Scaling & Parallel Execution

### Queue Configuration (Horizon)

```php
<?php
// config/horizon.php

return [
    'environments' => [
        'production' => [
            // Default agent workers
            'agent-workers' => [
                'connection' => 'redis',
                'queue' => ['agents-default'],
                'balance' => 'auto',
                'processes' => 10,
                'tries' => 1,
                'timeout' => 600,
                'memory' => 512,
            ],

            // Long-running agent workers (research, complex tasks)
            'agent-long-workers' => [
                'connection' => 'redis',
                'queue' => ['agents-long'],
                'balance' => 'simple',
                'processes' => 5,
                'tries' => 1,
                'timeout' => 1800, // 30 minutes
                'memory' => 1024,
            ],

            // Priority queue for premium users
            'agent-priority-workers' => [
                'connection' => 'redis',
                'queue' => ['agents-priority'],
                'balance' => 'auto',
                'processes' => 5,
                'tries' => 1,
                'timeout' => 600,
                'memory' => 512,
            ],
        ],
    ],
];
```

### Rate Limiting Per User

```php
<?php

namespace App\Services;

use App\Models\AgentExecution;
use Illuminate\Support\Facades\RateLimiter;

class AgentRateLimiter
{
    public function __construct(
        private array $limits = [
            'free' => ['max_concurrent' => 1, 'per_hour' => 10],
            'pro' => ['max_concurrent' => 5, 'per_hour' => 100],
            'enterprise' => ['max_concurrent' => 20, 'per_hour' => 1000],
        ],
    ) {}

    public function canStart(int $userId, string $tier = 'free'): bool
    {
        $limits = $this->limits[$tier] ?? $this->limits['free'];

        // Check concurrent limit
        $concurrent = AgentExecution::where('user_id', $userId)
            ->whereIn('status', ['pending', 'running', 'paused'])
            ->count();

        if ($concurrent >= $limits['max_concurrent']) {
            return false;
        }

        // Check hourly limit
        $key = "agent_rate:{$userId}";
        if (RateLimiter::tooManyAttempts($key, $limits['per_hour'])) {
            return false;
        }

        return true;
    }

    public function hit(int $userId): void
    {
        $key = "agent_rate:{$userId}";
        RateLimiter::hit($key, 3600); // 1 hour decay
    }

    public function remaining(int $userId, string $tier = 'free'): array
    {
        $limits = $this->limits[$tier] ?? $this->limits['free'];
        $key = "agent_rate:{$userId}";

        $concurrent = AgentExecution::where('user_id', $userId)
            ->whereIn('status', ['pending', 'running', 'paused'])
            ->count();

        return [
            'concurrent' => [
                'used' => $concurrent,
                'limit' => $limits['max_concurrent'],
                'remaining' => max(0, $limits['max_concurrent'] - $concurrent),
            ],
            'hourly' => [
                'used' => RateLimiter::attempts($key),
                'limit' => $limits['per_hour'],
                'remaining' => RateLimiter::remaining($key, $limits['per_hour']),
                'resets_at' => RateLimiter::availableIn($key),
            ],
        ];
    }
}
```

### Queue Priority Middleware

```php
<?php

namespace App\Http\Middleware;

use App\Services\AgentRateLimiter;
use Closure;
use Illuminate\Http\Request;

class CheckAgentRateLimit
{
    public function __construct(
        private AgentRateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tier = $user->subscription_tier ?? 'free';

        if (!$this->limiter->canStart($user->id, $tier)) {
            $remaining = $this->limiter->remaining($user->id, $tier);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'limits' => $remaining,
            ], 429);
        }

        return $next($request);
    }
}
```

### Dynamic Queue Selection

```php
<?php

namespace App\Services;

use App\Models\AgentExecution;
use App\Models\User;

class QueueSelector
{
    public function selectQueue(User $user, string $agentType, array $options = []): string
    {
        // Priority users get dedicated queue
        if ($user->subscription_tier === 'enterprise') {
            return 'agents-priority';
        }

        // Long-running agent types
        if (in_array($agentType, ['research', 'code-review', 'migration'])) {
            return 'agents-long';
        }

        // Estimated complexity-based routing
        $estimatedSteps = $options['estimated_steps'] ?? 20;
        if ($estimatedSteps > 50) {
            return 'agents-long';
        }

        return 'agents-default';
    }

    public function getQueueStats(): array
    {
        $queues = ['agents-default', 'agents-long', 'agents-priority'];
        $stats = [];

        foreach ($queues as $queue) {
            $stats[$queue] = [
                'pending' => \Queue::size($queue),
                'processing' => AgentExecution::where('status', 'running')
                    ->where('metadata->queue', $queue)
                    ->count(),
            ];
        }

        return $stats;
    }
}
```

---

## Event-Driven Awakening

### Awaitable Agent Job

```php
<?php

namespace App\Jobs;

use App\Models\AgentExecution;
use App\Models\AgentSignal;
use App\Services\AgentExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class AwaitableAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max for awaitable jobs

    public function __construct(
        public string $executionId,
        public ?string $awaitEventType = null,
    ) {}

    public function handle(AgentExecutionService $service): void
    {
        $execution = AgentExecution::findOrFail($this->executionId);

        // If awaiting specific event, wait for it
        if ($this->awaitEventType) {
            $this->waitForEvent($execution);
        }

        $service->run($execution);
    }

    private function waitForEvent(AgentExecution $execution): void
    {
        $execution->update([
            'status' => 'awaiting_event',
            'metadata->awaiting_event' => $this->awaitEventType,
        ]);

        $maxWaitSeconds = 1800; // 30 minutes max wait
        $waited = 0;
        $checkInterval = 5;

        while ($waited < $maxWaitSeconds) {
            // Check for the awaited signal
            $signal = AgentSignal::where('execution_id', $execution->id)
                ->where('signal_type', $this->awaitEventType)
                ->where('processed', false)
                ->first();

            if ($signal) {
                $signal->update(['processed' => true]);

                // Merge signal payload into execution metadata
                if ($signal->payload) {
                    $execution->update([
                        'metadata->event_payload' => $signal->payload,
                    ]);
                }

                return; // Event received, continue execution
            }

            // Check for cancel signal
            $cancelSignal = AgentSignal::where('execution_id', $execution->id)
                ->where('signal_type', 'cancel')
                ->where('processed', false)
                ->first();

            if ($cancelSignal) {
                $cancelSignal->update(['processed' => true]);
                throw new \RuntimeException('Execution cancelled while awaiting event');
            }

            sleep($checkInterval);
            $waited += $checkInterval;
        }

        throw new \RuntimeException("Timeout waiting for event: {$this->awaitEventType}");
    }
}
```

### Event Trigger Service

```php
<?php

namespace App\Services;

use App\Jobs\AwaitableAgentJob;
use App\Models\AgentExecution;
use App\Models\AgentSignal;

class AgentEventTrigger
{
    /**
     * Start an agent that will wait for a specific event before executing.
     */
    public function startAwaitingEvent(
        int $userId,
        string $agentType,
        array $input,
        string $awaitEventType,
    ): AgentExecution {
        $execution = AgentExecution::create([
            'id' => \Str::uuid(),
            'user_id' => $userId,
            'agent_type' => $agentType,
            'status' => 'awaiting_event',
            'input' => $input,
            'metadata' => [
                'awaiting_event' => $awaitEventType,
            ],
        ]);

        AwaitableAgentJob::dispatch($execution->id, $awaitEventType)
            ->onQueue('agents-long');

        return $execution;
    }

    /**
     * Trigger an event that awakens waiting agents.
     */
    public function triggerEvent(string $eventType, array $payload = [], ?int $userId = null): int
    {
        $query = AgentExecution::where('status', 'awaiting_event')
            ->where('metadata->awaiting_event', $eventType);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $awaitingExecutions = $query->get();
        $count = 0;

        foreach ($awaitingExecutions as $execution) {
            AgentSignal::create([
                'execution_id' => $execution->id,
                'signal_type' => $eventType,
                'payload' => $payload,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Schedule an agent to run at a specific time.
     */
    public function scheduleAt(
        int $userId,
        string $agentType,
        array $input,
        \DateTimeInterface $runAt,
    ): AgentExecution {
        $execution = AgentExecution::create([
            'id' => \Str::uuid(),
            'user_id' => $userId,
            'agent_type' => $agentType,
            'status' => 'scheduled',
            'input' => $input,
            'metadata' => [
                'scheduled_for' => $runAt->format('c'),
            ],
        ]);

        \App\Jobs\ExecuteAgentJob::dispatch($execution->id)
            ->delay($runAt)
            ->onQueue('agents-default');

        return $execution;
    }
}
```

### Webhook-Triggered Execution

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\AgentEventTrigger;
use Illuminate\Http\Request;

class AgentWebhookController extends Controller
{
    public function __construct(
        private AgentEventTrigger $eventTrigger,
    ) {}

    /**
     * Receive webhook and trigger waiting agents.
     *
     * POST /webhooks/agent-trigger
     * {
     *   "event_type": "github.push",
     *   "payload": { ... }
     * }
     */
    public function trigger(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string',
            'payload' => 'array',
            'user_id' => 'nullable|integer',
        ]);

        $count = $this->eventTrigger->triggerEvent(
            eventType: $request->event_type,
            payload: $request->payload ?? [],
            userId: $request->user_id,
        );

        return response()->json([
            'message' => "Triggered {$count} waiting agent(s)",
            'triggered_count' => $count,
        ]);
    }
}
```

---

## Long-Running Jobs

### Chunked Execution with Checkpoints

```php
<?php

namespace App\Jobs;

use App\Models\AgentExecution;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Data\AgentState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChunkedAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes per chunk

    private const STEPS_PER_CHUNK = 10;
    private const MAX_CHUNKS = 50;

    public function __construct(
        public string $executionId,
        public int $chunkNumber = 0,
    ) {}

    public function handle(): void
    {
        $execution = AgentExecution::findOrFail($this->executionId);

        if ($execution->status === 'cancelled') {
            return;
        }

        $agent = $this->buildAgent($execution);
        $state = $this->loadState($execution);

        $stepsInChunk = 0;
        $shouldContinue = true;

        foreach ($agent->iterator($state) as $currentState) {
            $stepsInChunk++;
            $state = $currentState;

            // Update progress
            $execution->increment('step_count');
            $execution->update([
                'token_usage' => $state->usage()->total(),
            ]);

            // Check if chunk complete
            if ($stepsInChunk >= self::STEPS_PER_CHUNK) {
                break;
            }

            // Check if agent is done
            if (!$agent->hasNextStep($state)) {
                $shouldContinue = false;
                break;
            }
        }

        // Save checkpoint
        $this->saveCheckpoint($execution, $state);

        if ($shouldContinue && $this->chunkNumber < self::MAX_CHUNKS) {
            // Dispatch next chunk
            self::dispatch($this->executionId, $this->chunkNumber + 1)
                ->onQueue('agents-long')
                ->delay(now()->addSeconds(1)); // Small delay to prevent queue flooding
        } else {
            // Execution complete
            $this->completeExecution($execution, $state);
        }
    }

    private function loadState(AgentExecution $execution): AgentState
    {
        if ($execution->state_snapshot) {
            return $this->deserializeState($execution->state_snapshot);
        }

        return AgentState::empty()->withMessages(
            \Cognesy\Messages\Messages::fromString($execution->input['prompt'] ?? '')
        );
    }

    private function saveCheckpoint(AgentExecution $execution, AgentState $state): void
    {
        $execution->update([
            'state_snapshot' => $this->serializeState($state),
            'metadata->last_checkpoint' => now()->toIso8601String(),
            'metadata->chunk_number' => $this->chunkNumber,
        ]);
    }

    private function completeExecution(AgentExecution $execution, AgentState $state): void
    {
        $output = $state->currentStep()?->outputMessages()->toString();

        $execution->update([
            'status' => 'completed',
            'completed_at' => now(),
            'output' => ['response' => $output],
        ]);

        broadcast(new \App\Events\AgentStatusChanged($execution));
    }

    // ... buildAgent, serializeState, deserializeState methods
}
```

### Subagent Research Pattern

```php
<?php

namespace App\Services;

use App\Jobs\ExecuteAgentJob;
use App\Models\AgentExecution;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Registry\AgentSpec;

class ResearchAgentService
{
    /**
     * Create a research agent with parallel subagent capability.
     *
     * Research tasks may spawn multiple subagents that run in parallel,
     * each potentially taking minutes to complete.
     */
    public function createResearchAgent(AgentExecution $execution): Agent
    {
        $registry = $this->buildSubagentRegistry();

        return AgentBuilder::base()
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\File\UseFileTools(
                storage_path('app/research/' . $execution->id)
            ))
            ->withCapability(UseSubagents::withDepth(
                maxDepth: 3,
                registry: $registry,
                summaryMaxChars: 12000,
            ))
            ->withCapability(new \Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning())
            ->withMaxSteps(100)
            ->withMaxTokens(100000)
            ->withTimeout(1800) // 30 minutes
            ->build();
    }

    private function buildSubagentRegistry(): AgentRegistry
    {
        $registry = new AgentRegistry();

        // Deep research subagent - for in-depth analysis
        $registry->register(new AgentSpec(
            name: 'deep-research',
            description: 'Performs deep research on a specific topic, analyzing multiple sources',
            systemPrompt: <<<PROMPT
You are a thorough research assistant. Given a topic:
1. Identify key aspects to investigate
2. Search for relevant information
3. Analyze and synthesize findings
4. Provide a comprehensive summary with citations
PROMPT,
            tools: ['read_file', 'search_files', 'write_file'],
        ));

        // Quick lookup subagent - for fast fact-checking
        $registry->register(new AgentSpec(
            name: 'quick-lookup',
            description: 'Fast fact-checking and quick information retrieval',
            systemPrompt: 'You are a fast lookup assistant. Quickly find and return specific facts.',
            tools: ['read_file', 'search_files'],
        ));

        // Synthesis subagent - for combining research from multiple sources
        $registry->register(new AgentSpec(
            name: 'synthesis',
            description: 'Synthesizes information from multiple research outputs into cohesive conclusions',
            systemPrompt: <<<PROMPT
You are a synthesis expert. Given multiple research findings:
1. Identify common themes and patterns
2. Resolve conflicting information
3. Create a unified, coherent narrative
4. Highlight key insights and conclusions
PROMPT,
            tools: ['read_file', 'write_file'],
        ));

        return $registry;
    }
}
```

### Timeout Recovery

```php
<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteAgentJob;
use App\Models\AgentExecution;
use Illuminate\Console\Command;

class RecoverStuckAgents extends Command
{
    protected $signature = 'agents:recover-stuck {--timeout=30}';
    protected $description = 'Recover agents stuck in running state';

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $threshold = now()->subMinutes($timeoutMinutes);

        $stuckExecutions = AgentExecution::where('status', 'running')
            ->where('updated_at', '<', $threshold)
            ->get();

        $this->info("Found {$stuckExecutions->count()} stuck executions");

        foreach ($stuckExecutions as $execution) {
            $this->line("Recovering: {$execution->id}");

            // Check if it has a checkpoint
            if ($execution->state_snapshot) {
                // Has checkpoint - restart from last state
                $execution->update(['status' => 'pending']);
                ExecuteAgentJob::dispatch($execution->id)->onQueue('agents-default');
                $this->info("  -> Restarted from checkpoint");
            } else {
                // No checkpoint - mark as failed
                $execution->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'metadata->error' => 'Execution timed out without checkpoint',
                ]);
                $this->warn("  -> Marked as failed (no checkpoint)");
            }
        }

        return Command::SUCCESS;
    }
}
```

### Scheduled Cleanup

```php
<?php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Recover stuck agents every 15 minutes
    $schedule->command('agents:recover-stuck --timeout=30')
        ->everyFifteenMinutes()
        ->withoutOverlapping();

    // Clean up old completed executions (keep 30 days)
    $schedule->command('model:prune', [
        '--model' => AgentExecution::class,
    ])->daily();

    // Archive old logs (keep 7 days inline, archive rest)
    $schedule->call(function () {
        AgentLog::where('created_at', '<', now()->subDays(7))
            ->whereHas('execution', fn($q) => $q->whereIn('status', ['completed', 'failed', 'cancelled']))
            ->delete();
    })->daily();
}
```

---

## Complete Implementation Example

### Full Agent Service Provider

```php
<?php

namespace App\Providers;

use App\Services\AgentExecutionService;
use App\Services\AgentEventTrigger;
use App\Services\AgentManager;
use App\Services\AgentRateLimiter;
use App\Services\QueueSelector;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentRateLimiter::class, function () {
            return new AgentRateLimiter(config('agents.rate_limits', []));
        });

        $this->app->singleton(QueueSelector::class);
        $this->app->singleton(AgentExecutionService::class);
        $this->app->singleton(AgentEventTrigger::class);

        $this->app->singleton(AgentManager::class, function ($app) {
            return new AgentManager(
                $app->make(AgentExecutionService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/agents.php' => config_path('agents.php'),
        ], 'agents-config');
    }
}
```

### Configuration File

```php
<?php
// config/agents.php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limits by Subscription Tier
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'free' => [
            'max_concurrent' => 1,
            'per_hour' => 10,
        ],
        'pro' => [
            'max_concurrent' => 5,
            'per_hour' => 100,
        ],
        'enterprise' => [
            'max_concurrent' => 20,
            'per_hour' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'default' => 'agents-default',
        'long_running' => 'agents-long',
        'priority' => 'agents-priority',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'default' => 600,       // 10 minutes
        'long_running' => 1800, // 30 minutes
        'max_wait_event' => 1800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_steps' => 100,
        'max_tokens' => 100000,
        'chunk_size' => 10, // Steps per chunk for long-running
        'max_chunks' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Paths
    |--------------------------------------------------------------------------
    */
    'workspace' => [
        'base_path' => storage_path('app/agent-workspaces'),
        'cleanup_after_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'code-assistant' => [
            'queue' => 'agents-default',
            'timeout' => 600,
            'max_steps' => 50,
        ],
        'research' => [
            'queue' => 'agents-long',
            'timeout' => 1800,
            'max_steps' => 100,
        ],
        'code-review' => [
            'queue' => 'agents-default',
            'timeout' => 300,
            'max_steps' => 30,
        ],
    ],
];
```

### Routes

```php
<?php
// routes/api.php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Admin\AgentLogsController;
use App\Http\Controllers\Webhooks\AgentWebhookController;
use App\Http\Middleware\CheckAgentRateLimit;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('agents')->group(function () {
        Route::get('/', [AgentController::class, 'index']);
        Route::post('/', [AgentController::class, 'store'])
            ->middleware(CheckAgentRateLimit::class);
        Route::get('/{execution}', [AgentController::class, 'show']);
        Route::post('/{execution}/pause', [AgentController::class, 'pause']);
        Route::post('/{execution}/resume', [AgentController::class, 'resume']);
        Route::post('/{execution}/cancel', [AgentController::class, 'cancel']);
        Route::post('/{execution}/input', [AgentController::class, 'sendInput']);
        Route::delete('/{execution}', [AgentController::class, 'forceKill']);
    });

    // Admin routes
    Route::middleware(['can:admin'])->prefix('admin/agents')->group(function () {
        Route::get('/', [AgentLogsController::class, 'index']);
        Route::get('/{execution}', [AgentLogsController::class, 'show']);
        Route::get('/{execution}/logs', [AgentLogsController::class, 'logs']);
        Route::get('/{execution}/stream', [AgentLogsController::class, 'stream']);
    });
});

// Webhooks (with signature verification)
Route::prefix('webhooks')->middleware(['verify.webhook'])->group(function () {
    Route::post('/agent-trigger', [AgentWebhookController::class, 'trigger']);
});
```

### Frontend Integration (Vue/React Example)

```typescript
// composables/useAgentExecution.ts
import { ref, onMounted, onUnmounted } from 'vue'
import Echo from 'laravel-echo'

interface AgentExecution {
  id: string
  status: string
  step_count: number
  token_usage: number
  output?: { response: string }
}

interface AgentStep {
  step_number: number
  step_type: string
  tool_names: string[]
  tokens_used: number
}

export function useAgentExecution(executionId: string) {
  const execution = ref<AgentExecution | null>(null)
  const steps = ref<AgentStep[]>([])
  const isLoading = ref(true)
  const error = ref<string | null>(null)

  let channel: any = null

  const fetchExecution = async () => {
    try {
      const response = await fetch(`/api/agents/${executionId}`)
      execution.value = await response.json()
    } catch (e) {
      error.value = 'Failed to load execution'
    } finally {
      isLoading.value = false
    }
  }

  const subscribe = () => {
    channel = (window as any).Echo.private(`agent.${executionId}`)
      .listen('.step.completed', (e: AgentStep) => {
        steps.value.push(e)
        if (execution.value) {
          execution.value.step_count = e.step_number
          execution.value.token_usage = e.tokens_used
        }
      })
      .listen('.status.changed', (e: Partial<AgentExecution>) => {
        if (execution.value) {
          Object.assign(execution.value, e)
        }
      })
  }

  const pause = async () => {
    await fetch(`/api/agents/${executionId}/pause`, { method: 'POST' })
  }

  const resume = async () => {
    await fetch(`/api/agents/${executionId}/resume`, { method: 'POST' })
  }

  const cancel = async () => {
    await fetch(`/api/agents/${executionId}/cancel`, { method: 'POST' })
  }

  onMounted(() => {
    fetchExecution()
    subscribe()
  })

  onUnmounted(() => {
    channel?.stopListening('.step.completed')
    channel?.stopListening('.status.changed')
  })

  return {
    execution,
    steps,
    isLoading,
    error,
    pause,
    resume,
    cancel,
  }
}
```

---

## Summary

| Feature | Implementation |
|---------|---------------|
| Job Execution | Laravel queues with Horizon |
| Status Communication | Database + WebSocket broadcasting |
| Logging | Structured logs in DB, SSE streaming |
| Pause/Resume | Signal queue + state serialization |
| Kill | Soft (signal) and hard (force) options |
| Scaling | Multiple queue workers, rate limiting |
| Event Awakening | Signal table + webhook triggers |
| Long-running | Chunked execution with checkpoints |

### Key Considerations

1. **State Serialization**: AgentState must be fully serializable for pause/resume
2. **Timeout Handling**: Use chunked execution for jobs > 10 minutes
3. **Rate Limiting**: Enforce per-user limits at both API and queue level
4. **Monitoring**: Use Horizon dashboard + custom admin UI for visibility
5. **Cleanup**: Schedule regular cleanup of old executions and logs
6. **Error Recovery**: Implement automatic stuck-job recovery
