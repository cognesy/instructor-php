# Advanced Patterns for Laravel Agent Deployment

Supplementary patterns for complex agent deployment scenarios.

---

## Multi-Tenant Agent Isolation

### Workspace Isolation

```php
<?php

namespace App\Services;

use App\Models\AgentExecution;
use App\Models\User;

class AgentWorkspaceManager
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = config('agents.workspace.base_path');
    }

    /**
     * Create isolated workspace for an execution.
     */
    public function createWorkspace(AgentExecution $execution): string
    {
        $path = $this->getWorkspacePath($execution);

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Copy user's project files if needed
        if ($execution->input['project_id'] ?? null) {
            $this->copyProjectFiles($execution, $path);
        }

        return $path;
    }

    /**
     * Clean up workspace after execution.
     */
    public function cleanupWorkspace(AgentExecution $execution): void
    {
        $path = $this->getWorkspacePath($execution);

        if (is_dir($path)) {
            $this->recursiveDelete($path);
        }
    }

    /**
     * Get workspace path following tenant isolation.
     */
    public function getWorkspacePath(AgentExecution $execution): string
    {
        // Structure: base/tenant_id/execution_id/
        return sprintf(
            '%s/%s/%s',
            $this->basePath,
            $execution->user->tenant_id ?? $execution->user_id,
            $execution->id
        );
    }

    /**
     * Enforce disk quota per tenant.
     */
    public function checkDiskQuota(User $user): bool
    {
        $tenantPath = sprintf('%s/%s', $this->basePath, $user->tenant_id ?? $user->id);
        $usedBytes = $this->getDirectorySize($tenantPath);
        $quotaBytes = $this->getQuotaForUser($user);

        return $usedBytes < $quotaBytes;
    }

    private function getQuotaForUser(User $user): int
    {
        return match ($user->subscription_tier) {
            'free' => 100 * 1024 * 1024,      // 100 MB
            'pro' => 1024 * 1024 * 1024,       // 1 GB
            'enterprise' => 10 * 1024 * 1024 * 1024, // 10 GB
            default => 100 * 1024 * 1024,
        };
    }

    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function recursiveDelete(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    private function copyProjectFiles(AgentExecution $execution, string $targetPath): void
    {
        // Implementation depends on how project files are stored
    }
}
```

### Sandboxed Bash Execution

```php
<?php

namespace App\Services;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

class SandboxPolicyFactory
{
    /**
     * Create execution policy based on user tier and context.
     */
    public function createPolicy(
        string $workspacePath,
        string $userTier,
        array $options = [],
    ): ExecutionPolicy {
        $basePolicy = ExecutionPolicy::in($workspacePath);

        // Tier-based restrictions
        $policy = match ($userTier) {
            'free' => $this->restrictedPolicy($basePolicy, $workspacePath),
            'pro' => $this->standardPolicy($basePolicy, $workspacePath),
            'enterprise' => $this->permissivePolicy($basePolicy, $workspacePath, $options),
            default => $this->restrictedPolicy($basePolicy, $workspacePath),
        };

        return $policy;
    }

    private function restrictedPolicy(ExecutionPolicy $base, string $workspace): ExecutionPolicy
    {
        return $base
            ->withTimeout(60)
            ->withNetwork(false)
            ->withReadablePaths($workspace, '/usr/share/dict', '/etc/passwd')
            ->withWritablePaths($workspace)
            ->withMaxMemory(256 * 1024 * 1024)  // 256 MB
            ->withMaxProcesses(5)
            ->withBlockedCommands(['curl', 'wget', 'ssh', 'scp', 'nc', 'netcat']);
    }

    private function standardPolicy(ExecutionPolicy $base, string $workspace): ExecutionPolicy
    {
        return $base
            ->withTimeout(120)
            ->withNetwork(false)  // Still no network by default
            ->withReadablePaths($workspace, '/usr/share', '/etc/passwd', '/etc/hosts')
            ->withWritablePaths($workspace)
            ->withMaxMemory(512 * 1024 * 1024)  // 512 MB
            ->withMaxProcesses(10);
    }

    private function permissivePolicy(ExecutionPolicy $base, string $workspace, array $options): ExecutionPolicy
    {
        return $base
            ->withTimeout($options['timeout'] ?? 300)
            ->withNetwork($options['allow_network'] ?? false)
            ->withReadablePaths($workspace, '/usr/share', '/etc', ...$options['extra_read_paths'] ?? [])
            ->withWritablePaths($workspace, ...$options['extra_write_paths'] ?? [])
            ->withMaxMemory(1024 * 1024 * 1024)  // 1 GB
            ->withMaxProcesses(20)
            ->inheritEnvironment($options['inherit_env'] ?? false);
    }
}
```

---

## Conversation Continuity

### Persistent Conversation Agent

```php
<?php

namespace App\Services;

use App\Models\AgentConversation;
use App\Models\AgentExecution;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

class ConversationAgentService
{
    /**
     * Continue an existing conversation or start new one.
     */
    public function continueConversation(
        int $userId,
        ?string $conversationId,
        string $userMessage,
        string $agentType = 'assistant',
    ): AgentExecution {
        $conversation = $conversationId
            ? AgentConversation::where('user_id', $userId)->findOrFail($conversationId)
            : $this->createConversation($userId, $agentType);

        // Build state from conversation history
        $state = $this->buildStateFromHistory($conversation, $userMessage);

        // Create new execution linked to conversation
        $execution = AgentExecution::create([
            'id' => \Str::uuid(),
            'user_id' => $userId,
            'agent_type' => $agentType,
            'status' => 'pending',
            'input' => ['prompt' => $userMessage],
            'metadata' => [
                'conversation_id' => $conversation->id,
                'turn_number' => $conversation->turn_count + 1,
            ],
            'state_snapshot' => $this->serializeState($state),
        ]);

        // Dispatch execution
        \App\Jobs\ExecuteAgentJob::dispatch($execution->id);

        return $execution;
    }

    /**
     * After execution completes, update conversation history.
     */
    public function appendToConversation(AgentExecution $execution): void
    {
        $conversationId = $execution->metadata['conversation_id'] ?? null;
        if (!$conversationId) {
            return;
        }

        $conversation = AgentConversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        // Get the response from completed execution
        $response = $execution->output['response'] ?? '';

        // Append to history
        $history = $conversation->message_history ?? [];
        $history[] = [
            'role' => 'user',
            'content' => $execution->input['prompt'],
            'timestamp' => $execution->created_at->toIso8601String(),
        ];
        $history[] = [
            'role' => 'assistant',
            'content' => $response,
            'execution_id' => $execution->id,
            'timestamp' => $execution->completed_at?->toIso8601String(),
        ];

        $conversation->update([
            'message_history' => $history,
            'turn_count' => $conversation->turn_count + 1,
            'last_activity_at' => now(),
        ]);
    }

    private function createConversation(int $userId, string $agentType): AgentConversation
    {
        return AgentConversation::create([
            'id' => \Str::uuid(),
            'user_id' => $userId,
            'agent_type' => $agentType,
            'message_history' => [],
            'turn_count' => 0,
            'last_activity_at' => now(),
        ]);
    }

    private function buildStateFromHistory(AgentConversation $conversation, string $newMessage): AgentState
    {
        $messages = [];

        // Add conversation history
        foreach ($conversation->message_history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Add new user message
        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];

        return AgentState::empty()->withMessages(Messages::fromArray($messages));
    }

    private function serializeState(AgentState $state): array
    {
        return [
            'messages' => $state->messages()->toArray(),
            'metadata' => $state->metadata()->toArray(),
        ];
    }
}
```

### Conversation Migration Schema

```php
<?php
// Migration: create_agent_conversations_table.php

Schema::create('agent_conversations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('agent_type');
    $table->string('title')->nullable();
    $table->json('message_history');
    $table->json('metadata')->nullable();
    $table->integer('turn_count')->default(0);
    $table->integer('total_tokens')->default(0);
    $table->timestamp('last_activity_at');
    $table->timestamps();

    $table->index(['user_id', 'last_activity_at']);
});

// Add conversation_id to executions
Schema::table('agent_executions', function (Blueprint $table) {
    $table->uuid('conversation_id')->nullable()->after('user_id');
    $table->foreign('conversation_id')
          ->references('id')
          ->on('agent_conversations')
          ->nullOnDelete();
});
```

---

## Agent Orchestration

### Multi-Agent Pipeline

```php
<?php

namespace App\Services;

use App\Jobs\ExecuteAgentJob;
use App\Models\AgentExecution;
use App\Models\AgentPipeline;

class AgentPipelineService
{
    /**
     * Create a pipeline of agents that execute in sequence.
     *
     * Example pipeline: Research -> Analyze -> Write -> Review
     */
    public function createPipeline(
        int $userId,
        array $stages,
        array $initialInput,
    ): AgentPipeline {
        $pipeline = AgentPipeline::create([
            'id' => \Str::uuid(),
            'user_id' => $userId,
            'stages' => $stages,
            'status' => 'pending',
            'current_stage' => 0,
            'initial_input' => $initialInput,
        ]);

        // Start first stage
        $this->startStage($pipeline, 0);

        return $pipeline;
    }

    /**
     * Continue pipeline after a stage completes.
     */
    public function advancePipeline(AgentPipeline $pipeline, AgentExecution $completedExecution): void
    {
        $currentStage = $pipeline->current_stage;
        $totalStages = count($pipeline->stages);

        if ($completedExecution->status === 'failed') {
            $pipeline->update([
                'status' => 'failed',
                'metadata->failed_at_stage' => $currentStage,
                'metadata->error' => $completedExecution->metadata['error'] ?? 'Unknown error',
            ]);
            return;
        }

        // Store stage output
        $stageOutputs = $pipeline->stage_outputs ?? [];
        $stageOutputs[$currentStage] = $completedExecution->output;
        $pipeline->update(['stage_outputs' => $stageOutputs]);

        // Check if more stages
        $nextStage = $currentStage + 1;
        if ($nextStage >= $totalStages) {
            $pipeline->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            return;
        }

        // Start next stage
        $this->startStage($pipeline, $nextStage);
    }

    private function startStage(AgentPipeline $pipeline, int $stageIndex): void
    {
        $stage = $pipeline->stages[$stageIndex];

        // Build input for this stage from previous outputs
        $input = $this->buildStageInput($pipeline, $stageIndex);

        $execution = AgentExecution::create([
            'id' => \Str::uuid(),
            'user_id' => $pipeline->user_id,
            'agent_type' => $stage['agent_type'],
            'status' => 'pending',
            'input' => $input,
            'metadata' => [
                'pipeline_id' => $pipeline->id,
                'stage_index' => $stageIndex,
                'stage_name' => $stage['name'] ?? "Stage {$stageIndex}",
            ],
        ]);

        $pipeline->update([
            'current_stage' => $stageIndex,
            'status' => 'running',
        ]);

        ExecuteAgentJob::dispatch($execution->id)
            ->onQueue($stage['queue'] ?? 'agents-default');
    }

    private function buildStageInput(AgentPipeline $pipeline, int $stageIndex): array
    {
        $stage = $pipeline->stages[$stageIndex];
        $stageOutputs = $pipeline->stage_outputs ?? [];

        // First stage uses initial input
        if ($stageIndex === 0) {
            return $pipeline->initial_input;
        }

        // Build prompt from template with previous outputs
        $template = $stage['prompt_template'] ?? '{previous_output}';
        $previousOutput = $stageOutputs[$stageIndex - 1]['response'] ?? '';

        $prompt = str_replace(
            ['{previous_output}', '{initial_input}'],
            [$previousOutput, $pipeline->initial_input['prompt'] ?? ''],
            $template
        );

        return [
            'prompt' => $prompt,
            'previous_outputs' => $stageOutputs,
        ];
    }
}
```

### Pipeline Schema

```php
<?php
// Migration: create_agent_pipelines_table.php

Schema::create('agent_pipelines', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('pending');
    $table->json('stages');           // Array of stage definitions
    $table->json('initial_input');
    $table->json('stage_outputs')->nullable();
    $table->integer('current_stage')->default(0);
    $table->json('metadata')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
});
```

---

## Distributed Agent Execution

### Redis-Based Coordination

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class DistributedAgentCoordinator
{
    private const LOCK_TTL = 300; // 5 minutes

    /**
     * Acquire exclusive lock for an execution.
     * Prevents same execution running on multiple workers.
     */
    public function acquireLock(string $executionId): bool
    {
        $lockKey = "agent_lock:{$executionId}";
        $workerId = $this->getWorkerId();

        // Try to set lock with NX (only if not exists)
        $acquired = Redis::set($lockKey, $workerId, 'NX', 'EX', self::LOCK_TTL);

        return (bool) $acquired;
    }

    /**
     * Release lock for an execution.
     */
    public function releaseLock(string $executionId): void
    {
        $lockKey = "agent_lock:{$executionId}";
        $workerId = $this->getWorkerId();

        // Only release if we own the lock (Lua script for atomicity)
        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

        Redis::eval($script, 1, $lockKey, $workerId);
    }

    /**
     * Extend lock TTL while execution is still running.
     */
    public function extendLock(string $executionId): bool
    {
        $lockKey = "agent_lock:{$executionId}";
        $workerId = $this->getWorkerId();

        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("expire", KEYS[1], ARGV[2])
            else
                return 0
            end
        LUA;

        return (bool) Redis::eval($script, 1, $lockKey, $workerId, self::LOCK_TTL);
    }

    /**
     * Publish execution progress for real-time monitoring.
     */
    public function publishProgress(string $executionId, array $progress): void
    {
        $channel = "agent_progress:{$executionId}";
        Redis::publish($channel, json_encode($progress));
    }

    /**
     * Subscribe to execution progress (for WebSocket gateway).
     */
    public function subscribeToProgress(string $executionId, callable $callback): void
    {
        $channel = "agent_progress:{$executionId}";

        Redis::subscribe([$channel], function ($message) use ($callback) {
            $callback(json_decode($message, true));
        });
    }

    /**
     * Check if execution is being processed by another worker.
     */
    public function isLocked(string $executionId): bool
    {
        $lockKey = "agent_lock:{$executionId}";
        return (bool) Redis::exists($lockKey);
    }

    private function getWorkerId(): string
    {
        return gethostname() . ':' . getmypid();
    }
}
```

### Lock-Aware Job

```php
<?php

namespace App\Jobs;

use App\Models\AgentExecution;
use App\Services\AgentExecutionService;
use App\Services\DistributedAgentCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DistributedAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public string $executionId,
    ) {}

    public function handle(
        AgentExecutionService $service,
        DistributedAgentCoordinator $coordinator,
    ): void {
        // Try to acquire lock
        if (!$coordinator->acquireLock($this->executionId)) {
            // Another worker is handling this, skip
            return;
        }

        try {
            $execution = AgentExecution::findOrFail($this->executionId);

            // Run with periodic lock extension
            $this->runWithLockExtension($service, $coordinator, $execution);

        } finally {
            $coordinator->releaseLock($this->executionId);
        }
    }

    private function runWithLockExtension(
        AgentExecutionService $service,
        DistributedAgentCoordinator $coordinator,
        AgentExecution $execution,
    ): void {
        // Start background lock extension
        $shouldStop = false;
        $lockExtender = function () use ($coordinator, &$shouldStop) {
            while (!$shouldStop) {
                sleep(60); // Extend every minute
                if (!$shouldStop) {
                    $coordinator->extendLock($this->executionId);
                }
            }
        };

        // Note: In production, use pcntl_fork or Swoole for true background execution
        // This is simplified for illustration
        $service->run($execution);
        $shouldStop = true;
    }
}
```

---

## Monitoring & Observability

### OpenTelemetry Integration

```php
<?php

namespace App\Services;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;

class AgentTracer
{
    private TracerInterface $tracer;

    public function __construct()
    {
        $this->tracer = \OpenTelemetry\API\Globals::tracerProvider()
            ->getTracer('agent-service');
    }

    public function traceExecution(string $executionId, callable $operation): mixed
    {
        $span = $this->tracer->spanBuilder('agent.execution')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('agent.execution_id', $executionId)
            ->startSpan();

        $scope = $span->activate();

        try {
            $result = $operation();
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    public function traceStep(int $stepNumber, string $stepType, callable $operation): mixed
    {
        $span = $this->tracer->spanBuilder('agent.step')
            ->setAttribute('agent.step_number', $stepNumber)
            ->setAttribute('agent.step_type', $stepType)
            ->startSpan();

        $scope = $span->activate();

        try {
            return $operation();
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    public function traceToolCall(string $toolName, callable $operation): mixed
    {
        $span = $this->tracer->spanBuilder('agent.tool_call')
            ->setAttribute('agent.tool_name', $toolName)
            ->startSpan();

        $scope = $span->activate();
        $startTime = microtime(true);

        try {
            $result = $operation();
            $span->setAttribute('agent.tool_duration_ms', (microtime(true) - $startTime) * 1000);
            return $result;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
```

### Prometheus Metrics

```php
<?php

namespace App\Services;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

class AgentMetrics
{
    private Counter $executionsTotal;
    private Counter $stepsTotal;
    private Counter $toolCallsTotal;
    private Gauge $activeExecutions;
    private Histogram $executionDuration;
    private Histogram $stepDuration;
    private Histogram $tokenUsage;

    public function __construct(CollectorRegistry $registry)
    {
        $this->executionsTotal = $registry->getOrRegisterCounter(
            'agent',
            'executions_total',
            'Total number of agent executions',
            ['agent_type', 'status']
        );

        $this->stepsTotal = $registry->getOrRegisterCounter(
            'agent',
            'steps_total',
            'Total number of agent steps',
            ['agent_type', 'step_type']
        );

        $this->toolCallsTotal = $registry->getOrRegisterCounter(
            'agent',
            'tool_calls_total',
            'Total number of tool calls',
            ['tool_name', 'status']
        );

        $this->activeExecutions = $registry->getOrRegisterGauge(
            'agent',
            'active_executions',
            'Number of currently active executions',
            ['agent_type']
        );

        $this->executionDuration = $registry->getOrRegisterHistogram(
            'agent',
            'execution_duration_seconds',
            'Duration of agent executions',
            ['agent_type'],
            [1, 5, 10, 30, 60, 120, 300, 600]
        );

        $this->stepDuration = $registry->getOrRegisterHistogram(
            'agent',
            'step_duration_seconds',
            'Duration of agent steps',
            ['step_type'],
            [0.1, 0.5, 1, 2, 5, 10, 30]
        );

        $this->tokenUsage = $registry->getOrRegisterHistogram(
            'agent',
            'token_usage',
            'Token usage per execution',
            ['agent_type'],
            [100, 500, 1000, 5000, 10000, 50000, 100000]
        );
    }

    public function recordExecutionStart(string $agentType): void
    {
        $this->activeExecutions->inc(['agent_type' => $agentType]);
    }

    public function recordExecutionEnd(string $agentType, string $status, float $duration, int $tokens): void
    {
        $this->executionsTotal->inc(['agent_type' => $agentType, 'status' => $status]);
        $this->activeExecutions->dec(['agent_type' => $agentType]);
        $this->executionDuration->observe($duration, ['agent_type' => $agentType]);
        $this->tokenUsage->observe($tokens, ['agent_type' => $agentType]);
    }

    public function recordStep(string $agentType, string $stepType, float $duration): void
    {
        $this->stepsTotal->inc(['agent_type' => $agentType, 'step_type' => $stepType]);
        $this->stepDuration->observe($duration, ['step_type' => $stepType]);
    }

    public function recordToolCall(string $toolName, bool $success): void
    {
        $this->toolCallsTotal->inc([
            'tool_name' => $toolName,
            'status' => $success ? 'success' : 'error',
        ]);
    }
}
```

---

## Cost Tracking & Billing

### Token Usage Tracking

```php
<?php

namespace App\Services;

use App\Models\AgentExecution;
use App\Models\TokenUsageRecord;
use App\Models\User;

class TokenBillingService
{
    private array $pricing = [
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],      // per 1K tokens
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
    ];

    /**
     * Record token usage and calculate cost.
     */
    public function recordUsage(
        AgentExecution $execution,
        int $inputTokens,
        int $outputTokens,
        string $model,
    ): TokenUsageRecord {
        $cost = $this->calculateCost($inputTokens, $outputTokens, $model);

        return TokenUsageRecord::create([
            'user_id' => $execution->user_id,
            'execution_id' => $execution->id,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $cost,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get user's usage summary for billing period.
     */
    public function getUserUsageSummary(User $user, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $usage = TokenUsageRecord::where('user_id', $user->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->selectRaw('
                model,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cost_usd) as total_cost,
                COUNT(*) as request_count
            ')
            ->groupBy('model')
            ->get();

        return [
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'by_model' => $usage->toArray(),
            'totals' => [
                'input_tokens' => $usage->sum('total_input_tokens'),
                'output_tokens' => $usage->sum('total_output_tokens'),
                'cost_usd' => $usage->sum('total_cost'),
                'requests' => $usage->sum('request_count'),
            ],
        ];
    }

    /**
     * Check if user has sufficient credits/quota.
     */
    public function checkBudget(User $user, int $estimatedTokens): bool
    {
        $monthlyBudget = $user->monthly_token_budget ?? PHP_INT_MAX;
        $currentUsage = $this->getCurrentMonthUsage($user);

        return ($currentUsage + $estimatedTokens) <= $monthlyBudget;
    }

    private function calculateCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $pricing = $this->pricing[$model] ?? $this->pricing['claude-3-sonnet'];

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    private function getCurrentMonthUsage(User $user): int
    {
        return TokenUsageRecord::where('user_id', $user->id)
            ->whereBetween('recorded_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum(\DB::raw('input_tokens + output_tokens'));
    }
}
```

---

## Summary

| Pattern | Use Case |
|---------|----------|
| Multi-Tenant Isolation | SaaS with multiple users/organizations |
| Conversation Continuity | Chat-based agents with history |
| Agent Orchestration | Multi-step pipelines, parallel agents |
| Distributed Execution | High availability, no duplicate runs |
| Observability | Production monitoring, debugging |
| Cost Tracking | Usage billing, budget enforcement |

These patterns build on the base implementation to handle enterprise-grade requirements.
