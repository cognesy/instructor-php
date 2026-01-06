# Agent-Based Assistant Integration for Laravel Platform

**Date**: 2026-01-06
**Context**: Integration of instructor-php agent system into partnerspot/app-platform
**Goal**: Replace basic instructor inference API with full agent-based assistant featuring real-time event streaming

---

## Current State Analysis

### Existing Implementation (`platform-feat-assistant`)

**Problem**: Extremely limited capability using direct instructor inference API:
- No memory/state persistence between messages
- No iterative agent reasoning
- No tool use capability
- No real-time feedback to user
- Each message is independent, no conversation continuity

### Requirements for New System

1. **Agent Persistence**: Each assistant session = unique agent instance with ID
2. **Session Management**: Create new sessions with fresh agent instances
3. **Event Streaming**: Real-time status updates during agent execution
4. **UI Integration**: Status updates appear as separate spans/divs in chat
5. **Real-time Communication**: Laravel Echo for server-to-client updates
6. **State Continuity**: Messages within session reuse same agent state
7. **Async Execution**: Queue jobs for agent processing

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                          │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │ Chat Window │  │ Status Spans │  │ Laravel Echo Client  │  │
│  └─────────────┘  └──────────────┘  └──────────────────────┘  │
└────────────────────────────┬────────────────────────────────────┘
                             │ WebSocket / SSE
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      LARAVEL APPLICATION                        │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    API Controllers                        │  │
│  │  • POST /assistant/sessions (create)                     │  │
│  │  • POST /assistant/sessions/{id}/messages (send)        │  │
│  │  • GET  /assistant/sessions/{id} (retrieve)             │  │
│  └────────────────────┬─────────────────────────────────────┘  │
│                       │                                         │
│  ┌────────────────────▼─────────────────────────────────────┐  │
│  │              AssistantService                            │  │
│  │  • createSession()                                       │  │
│  │  • sendMessage()                                         │  │
│  │  • handleAgentEvents()                                   │  │
│  └────────────────────┬─────────────────────────────────────┘  │
│                       │                                         │
│  ┌────────────────────▼─────────────────────────────────────┐  │
│  │              Queue Job: ProcessAgentMessage              │  │
│  │  • Load AgentExecution + previous state                  │  │
│  │  • Resolve agent via AgentContractRegistry               │  │
│  │  • Run agent iterator with event streaming               │  │
│  │  • Broadcast events via Laravel Echo                     │  │
│  │  • Persist state snapshot + output                       │  │
│  └────────────────────┬─────────────────────────────────────┘  │
│                       │                                         │
│  ┌────────────────────▼─────────────────────────────────────┐  │
│  │           AgentExecutionService                          │  │
│  │  • buildAgent() - resolve via registry                   │  │
│  │  • executeWithEvents() - run iterator                    │  │
│  │  • persistSnapshot() - save state                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Event Broadcasting                      │  │
│  │  • AgentStatusUpdated (thinking, tool use, etc)          │  │
│  │  • AgentStepCompleted (step summary)                     │  │
│  │  • AgentMessageCompleted (final response)                │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                         DATABASE                                │
│  • assistant_sessions                                           │
│  • assistant_messages                                           │
│  • agent_executions                                             │
│  • agent_state_snapshots                                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### 1. `assistant_sessions`

Tracks chat sessions, each linked to one agent instance.

```php
Schema::create('assistant_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();

    // Agent configuration
    $table->string('agent_name'); // e.g., 'code-assistant', 'research-assistant'
    $table->uuid('agent_execution_id')->unique(); // Links to current agent instance
    $table->json('agent_config')->nullable(); // Additional agent parameters

    // Session metadata
    $table->string('title')->nullable();
    $table->enum('status', ['active', 'paused', 'completed', 'failed'])->default('active');
    $table->json('metadata')->nullable(); // Context, tags, etc.

    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'status', 'created_at']);
    $table->foreign('agent_execution_id')->references('id')->on('agent_executions');
});
```

### 2. `assistant_messages`

Individual messages within a session.

```php
Schema::create('assistant_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('session_id')->constrained('assistant_sessions')->cascadeOnDelete();

    // Message content
    $table->enum('role', ['user', 'assistant', 'system'])->default('user');
    $table->text('content');
    $table->json('metadata')->nullable(); // Attachments, formatting, etc.

    // Agent execution tracking (for assistant messages)
    $table->uuid('agent_execution_id')->nullable();
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

    // Metrics
    $table->integer('token_count')->nullable();
    $table->integer('step_count')->nullable();
    $table->decimal('execution_time', 8, 2)->nullable(); // seconds

    $table->timestamps();

    $table->index(['session_id', 'created_at']);
    $table->foreign('agent_execution_id')->references('id')->on('agent_executions');
});
```

### 3. `agent_executions`

Core agent execution tracking (from Laravel integration spec).

```php
Schema::create('agent_executions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    // Agent identity
    $table->string('agent_name'); // Resolves via AgentContractRegistry
    $table->json('agent_config')->nullable(); // Constructor params

    // Execution state
    $table->enum('status', [
        'pending',
        'running',
        'paused',
        'completed',
        'failed',
        'cancelled'
    ])->default('pending');

    // Input/Output
    $table->json('input'); // Initial user message + context
    $table->json('output')->nullable(); // Final agent response

    // State persistence
    $table->binary('state_snapshot')->nullable(); // Serialized AgentState
    $table->integer('step_count')->default(0);
    $table->json('token_usage')->nullable(); // {input: X, output: Y, total: Z}

    // Error handling
    $table->text('error_message')->nullable();
    $table->json('error_context')->nullable();

    // Metadata
    $table->json('metadata')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();

    $table->timestamps();

    $table->index(['user_id', 'status', 'created_at']);
    $table->index(['agent_name', 'status']);
});
```

### 4. `agent_status_events`

Real-time status updates during execution (for UI feedback).

```php
Schema::create('agent_status_events', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('agent_execution_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('session_id')->nullable()->constrained('assistant_sessions')->cascadeOnDelete();

    // Event details
    $table->string('event_type'); // 'thinking', 'tool_call', 'tool_result', 'step_complete'
    $table->text('message'); // Human-readable status
    $table->json('data')->nullable(); // Structured event data

    // Ordering
    $table->integer('step_number')->nullable();
    $table->integer('sequence')->nullable(); // Order within step

    $table->timestamp('created_at');

    $table->index(['agent_execution_id', 'created_at']);
    $table->index(['session_id', 'created_at']);
});
```

---

## Eloquent Models

### AssistantSession

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistantSession extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'team_id',
        'agent_name',
        'agent_execution_id',
        'agent_config',
        'title',
        'status',
        'metadata',
    ];

    protected $casts = [
        'id' => 'string',
        'agent_execution_id' => 'string',
        'agent_config' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecution::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'session_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(AgentStatusEvent::class, 'session_id');
    }
}
```

### AssistantMessage

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'metadata',
        'agent_execution_id',
        'status',
        'token_count',
        'step_count',
        'execution_time',
    ];

    protected $casts = [
        'metadata' => 'array',
        'execution_time' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class);
    }

    public function agentExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecution::class);
    }
}
```

### AgentExecution

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentExecution extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'agent_name',
        'agent_config',
        'status',
        'input',
        'output',
        'state_snapshot',
        'step_count',
        'token_usage',
        'error_message',
        'error_context',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'agent_config' => 'array',
        'input' => 'array',
        'output' => 'array',
        'token_usage' => 'array',
        'error_context' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(AgentStatusEvent::class);
    }
}
```

### AgentStatusEvent

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_execution_id',
        'session_id',
        'event_type',
        'message',
        'data',
        'step_number',
        'sequence',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public function agentExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecution::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class);
    }
}
```

---

## Service Layer

### AssistantService

Core orchestration service for assistant operations.

```php
<?php

namespace App\Services\Assistant;

use App\Events\AgentMessageCompleted;
use App\Events\AgentStatusUpdated;
use App\Jobs\ProcessAgentMessage;
use App\Models\AgentExecution;
use App\Models\AssistantMessage;
use App\Models\AssistantSession;
use Illuminate\Support\Str;

class AssistantService
{
    public function __construct(
        private AgentExecutionService $agentExecutionService
    ) {}

    /**
     * Create a new assistant session with a fresh agent instance.
     */
    public function createSession(
        int $userId,
        string $agentName,
        ?array $agentConfig = null,
        ?int $teamId = null,
        ?string $title = null
    ): AssistantSession {
        // Create agent execution record
        $agentExecution = AgentExecution::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'agent_name' => $agentName,
            'agent_config' => $agentConfig ?? [],
            'status' => 'pending',
            'input' => [],
            'metadata' => [
                'session_type' => 'assistant',
                'created_via' => 'web',
            ],
        ]);

        // Create session linked to agent
        $session = AssistantSession::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'agent_name' => $agentName,
            'agent_execution_id' => $agentExecution->id,
            'agent_config' => $agentConfig,
            'title' => $title ?? "New {$agentName} session",
            'status' => 'active',
        ]);

        return $session;
    }

    /**
     * Send a user message and trigger agent processing.
     */
    public function sendMessage(
        AssistantSession $session,
        string $content,
        ?array $metadata = null
    ): AssistantMessage {
        // Create user message
        $userMessage = AssistantMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $content,
            'metadata' => $metadata,
            'status' => 'completed',
        ]);

        // Create agent execution for this response
        $agentExecution = AgentExecution::create([
            'id' => Str::uuid(),
            'user_id' => $session->user_id,
            'agent_name' => $session->agent_name,
            'agent_config' => $session->agent_config ?? [],
            'status' => 'pending',
            'input' => [
                'message' => $content,
                'session_id' => $session->id,
                'conversation_history' => $this->getConversationHistory($session),
            ],
            'metadata' => [
                'session_id' => $session->id,
                'user_message_id' => $userMessage->id,
            ],
        ]);

        // Create placeholder assistant message
        $assistantMessage = AssistantMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => '', // Will be filled by agent
            'agent_execution_id' => $agentExecution->id,
            'status' => 'pending',
        ]);

        // Dispatch async job
        ProcessAgentMessage::dispatch(
            $agentExecution->id,
            $session->id,
            $assistantMessage->id
        )->onQueue('agents');

        return $assistantMessage;
    }

    /**
     * Get conversation history for context.
     */
    private function getConversationHistory(AssistantSession $session, int $limit = 10): array
    {
        return $session->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();
    }

    /**
     * Handle session closure.
     */
    public function closeSession(AssistantSession $session): void
    {
        $session->update(['status' => 'completed']);

        // Optionally cancel any pending executions
        AgentExecution::where('id', $session->agent_execution_id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
```

### AgentExecutionService

Handles agent lifecycle: instantiation, execution, state management.

```php
<?php

namespace App\Services\Assistant;

use App\Events\AgentStepCompleted;
use App\Events\AgentStatusUpdated;
use App\Models\AgentExecution;
use App\Models\AgentStatusEvent;
use Cognesy\Addons\Agent\Contracts\AgentContract;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Registry\AgentContractRegistry;
use Cognesy\Events\Event;
use Illuminate\Support\Facades\Log;

class AgentExecutionService
{
    public function __construct(
        private AgentContractRegistry $agentRegistry
    ) {}

    /**
     * Resolve agent from registry (deterministic).
     */
    public function buildAgent(string $agentName, array $config = []): AgentContract
    {
        $agent = $this->agentRegistry->get($agentName);

        if (!$agent) {
            throw new \RuntimeException("Agent '{$agentName}' not found in registry");
        }

        return $agent;
    }

    /**
     * Execute agent with event streaming.
     */
    public function executeWithEvents(
        AgentExecution $execution,
        ?string $sessionId = null
    ): AgentState {
        try {
            $execution->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Resolve agent
            $agent = $this->buildAgent(
                $execution->agent_name,
                $execution->agent_config ?? []
            );

            // Build or restore state
            $state = $this->buildState($execution);

            // Attach event handlers for real-time updates
            $agent->wiretap(function (Event $event) use ($execution, $sessionId) {
                $this->handleAgentEvent($event, $execution, $sessionId);
            });

            // Execute agent
            $finalState = $agent->run($state);

            // Persist results
            $this->persistSnapshot($execution, $finalState);

            return $finalState;

        } catch (\Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'error_context' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
                'completed_at' => now(),
            ]);

            Log::error('Agent execution failed', [
                'execution_id' => $execution->id,
                'agent_name' => $execution->agent_name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build AgentState from execution input or restore from snapshot.
     */
    private function buildState(AgentExecution $execution): AgentState
    {
        // If there's a snapshot, restore from it
        if ($execution->state_snapshot) {
            return unserialize($execution->state_snapshot);
        }

        // Otherwise, create new state from input
        $input = $execution->input;

        return AgentState::create(
            messages: $input['conversation_history'] ?? [],
            context: [
                'user_message' => $input['message'] ?? '',
                'session_id' => $input['session_id'] ?? null,
            ]
        );
    }

    /**
     * Persist final state snapshot.
     */
    private function persistSnapshot(AgentExecution $execution, AgentState $state): void
    {
        $execution->update([
            'status' => 'completed',
            'state_snapshot' => serialize($state),
            'step_count' => $state->stepCount(),
            'token_usage' => [
                'input' => $state->inputTokens(),
                'output' => $state->outputTokens(),
                'total' => $state->totalTokens(),
            ],
            'output' => [
                'response' => $state->finalResponse(),
                'metadata' => $state->metadata(),
            ],
            'completed_at' => now(),
        ]);
    }

    /**
     * Handle agent events and broadcast them.
     */
    private function handleAgentEvent(
        Event $event,
        AgentExecution $execution,
        ?string $sessionId
    ): void {
        $eventType = $this->mapEventType($event);
        $message = $this->extractEventMessage($event);
        $data = $this->extractEventData($event);

        // Persist event
        $statusEvent = AgentStatusEvent::create([
            'agent_execution_id' => $execution->id,
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'message' => $message,
            'data' => $data,
            'step_number' => $data['step_number'] ?? null,
            'sequence' => $data['sequence'] ?? null,
            'created_at' => now(),
        ]);

        // Broadcast to frontend
        broadcast(new AgentStatusUpdated(
            $execution->id,
            $sessionId,
            $eventType,
            $message,
            $data
        ));
    }

    /**
     * Map library event to our event type taxonomy.
     */
    private function mapEventType(Event $event): string
    {
        $class = get_class($event);

        return match (true) {
            str_contains($class, 'ThinkingStarted') => 'thinking',
            str_contains($class, 'ToolCallStarted') => 'tool_call',
            str_contains($class, 'ToolCallCompleted') => 'tool_result',
            str_contains($class, 'StepCompleted') => 'step_complete',
            str_contains($class, 'AgentCompleted') => 'agent_complete',
            default => 'unknown',
        };
    }

    /**
     * Extract human-readable message from event.
     */
    private function extractEventMessage(Event $event): string
    {
        // Customize based on event type
        // This is a placeholder - actual implementation depends on library events
        return $event->message ?? get_class($event);
    }

    /**
     * Extract structured data from event.
     */
    private function extractEventData(Event $event): array
    {
        // Extract relevant fields from event
        // This is a placeholder - actual implementation depends on library events
        return [
            'event_class' => get_class($event),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

---

## Queue Job

### ProcessAgentMessage

Async job that processes a single user message through the agent.

```php
<?php

namespace App\Jobs;

use App\Events\AgentMessageCompleted;
use App\Models\AgentExecution;
use App\Models\AssistantMessage;
use App\Models\AssistantSession;
use App\Services\Assistant\AgentExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAgentMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public string $executionId,
        public string $sessionId,
        public int $messageId
    ) {}

    public function handle(AgentExecutionService $executionService): void
    {
        $execution = AgentExecution::findOrFail($this->executionId);
        $message = AssistantMessage::findOrFail($this->messageId);
        $session = AssistantSession::findOrFail($this->sessionId);

        // Check if cancelled
        if ($execution->status === 'cancelled') {
            Log::info('Agent execution cancelled, skipping', [
                'execution_id' => $this->executionId,
            ]);

            $message->update(['status' => 'failed']);
            return;
        }

        try {
            $message->update(['status' => 'processing']);

            // Execute agent with event streaming
            $finalState = $executionService->executeWithEvents(
                $execution,
                $this->sessionId
            );

            // Extract response
            $response = $finalState->finalResponse();

            // Update message with response
            $message->update([
                'content' => $response,
                'status' => 'completed',
                'step_count' => $execution->step_count,
                'token_count' => $execution->token_usage['total'] ?? null,
                'execution_time' => now()->diffInSeconds($execution->started_at),
            ]);

            // Broadcast completion
            broadcast(new AgentMessageCompleted(
                $this->sessionId,
                $this->messageId,
                $response
            ));

            Log::info('Agent message processed successfully', [
                'execution_id' => $this->executionId,
                'session_id' => $this->sessionId,
                'message_id' => $this->messageId,
                'steps' => $execution->step_count,
            ]);

        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'content' => 'I encountered an error while processing your message. Please try again.',
            ]);

            Log::error('Agent message processing failed', [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAgentMessage job failed', [
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Events & Broadcasting

### AgentStatusUpdated

Real-time status update during agent execution.

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $executionId,
        public ?string $sessionId,
        public string $eventType,
        public string $message,
        public array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("assistant.session.{$this->sessionId}");
    }

    public function broadcastAs(): string
    {
        return 'agent.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->executionId,
            'event_type' => $this->eventType,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

### AgentMessageCompleted

Final response ready.

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentMessageCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $messageId,
        public string $response
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("assistant.session.{$this->sessionId}");
    }

    public function broadcastAs(): string
    {
        return 'agent.message.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'response' => $this->response,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

---

## API Routes & Controllers

### Routes

```php
<?php

// routes/api.php

use App\Http\Controllers\AssistantController;

Route::middleware(['auth:sanctum'])->prefix('assistant')->group(function () {
    // Session management
    Route::post('sessions', [AssistantController::class, 'createSession']);
    Route::get('sessions', [AssistantController::class, 'listSessions']);
    Route::get('sessions/{session}', [AssistantController::class, 'getSession']);
    Route::delete('sessions/{session}', [AssistantController::class, 'closeSession']);

    // Messaging
    Route::post('sessions/{session}/messages', [AssistantController::class, 'sendMessage']);
    Route::get('sessions/{session}/messages', [AssistantController::class, 'getMessages']);

    // Agent control (optional)
    Route::post('sessions/{session}/pause', [AssistantController::class, 'pauseSession']);
    Route::post('sessions/{session}/resume', [AssistantController::class, 'resumeSession']);
});
```

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\AssistantSessionResource;
use App\Http\Resources\AssistantMessageResource;
use App\Models\AssistantSession;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistantService
    ) {}

    public function createSession(CreateSessionRequest $request): JsonResponse
    {
        $session = $this->assistantService->createSession(
            userId: $request->user()->id,
            agentName: $request->input('agent_name', 'general-assistant'),
            agentConfig: $request->input('agent_config'),
            teamId: $request->input('team_id'),
            title: $request->input('title')
        );

        return response()->json([
            'data' => new AssistantSessionResource($session),
        ], 201);
    }

    public function listSessions(): JsonResponse
    {
        $sessions = AssistantSession::where('user_id', auth()->id())
            ->with(['agentExecution', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => AssistantSessionResource::collection($sessions),
        ]);
    }

    public function getSession(AssistantSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $session->load(['messages', 'statusEvents']);

        return response()->json([
            'data' => new AssistantSessionResource($session),
        ]);
    }

    public function sendMessage(SendMessageRequest $request, AssistantSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $message = $this->assistantService->sendMessage(
            session: $session,
            content: $request->input('content'),
            metadata: $request->input('metadata')
        );

        return response()->json([
            'data' => new AssistantMessageResource($message),
        ], 201);
    }

    public function getMessages(AssistantSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $messages = $session->messages()
            ->with('agentExecution')
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'data' => AssistantMessageResource::collection($messages),
        ]);
    }

    public function closeSession(AssistantSession $session): JsonResponse
    {
        $this->authorize('delete', $session);

        $this->assistantService->closeSession($session);

        return response()->json([
            'message' => 'Session closed successfully',
        ]);
    }
}
```

---

## Frontend Integration

### Vue/React Component (Conceptual)

```javascript
// AssistantChat.vue / AssistantChat.jsx

import { useEffect, useState } from 'react';
import Echo from 'laravel-echo';

export default function AssistantChat({ sessionId }) {
    const [messages, setMessages] = useState([]);
    const [statusUpdates, setStatusUpdates] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);

    useEffect(() => {
        // Subscribe to session channel
        const channel = window.Echo.channel(`assistant.session.${sessionId}`);

        // Listen for status updates
        channel.listen('.agent.status.updated', (event) => {
            setStatusUpdates(prev => [...prev, {
                id: `status-${Date.now()}`,
                type: event.event_type,
                message: event.message,
                data: event.data,
                timestamp: event.timestamp
            }]);
        });

        // Listen for message completion
        channel.listen('.agent.message.completed', (event) => {
            setIsProcessing(false);
            setStatusUpdates([]); // Clear status updates

            // Add final message
            setMessages(prev => [...prev, {
                id: event.message_id,
                role: 'assistant',
                content: event.response,
                timestamp: event.timestamp
            }]);
        });

        return () => {
            channel.stopListening('.agent.status.updated');
            channel.stopListening('.agent.message.completed');
        };
    }, [sessionId]);

    const sendMessage = async (content) => {
        // Add user message to UI immediately
        setMessages(prev => [...prev, {
            role: 'user',
            content,
            timestamp: new Date().toISOString()
        }]);

        setIsProcessing(true);

        // Send to API
        await fetch(`/api/assistant/sessions/${sessionId}/messages`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content })
        });
    };

    return (
        <div className="assistant-chat">
            <div className="messages">
                {messages.map(msg => (
                    <div key={msg.id} className={`message message-${msg.role}`}>
                        <div className="message-content">{msg.content}</div>
                    </div>
                ))}

                {isProcessing && (
                    <div className="agent-processing">
                        <div className="status-updates">
                            {statusUpdates.map(update => (
                                <div key={update.id} className={`status-update status-${update.type}`}>
                                    <span className="status-icon">{getStatusIcon(update.type)}</span>
                                    <span className="status-message">{update.message}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <MessageInput onSend={sendMessage} disabled={isProcessing} />
        </div>
    );
}

function getStatusIcon(type) {
    const icons = {
        thinking: '🤔',
        tool_call: '🔧',
        tool_result: '✅',
        step_complete: '📝',
        agent_complete: '✨'
    };
    return icons[type] || '•';
}
```

### Laravel Echo Configuration

```javascript
// bootstrap.js

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    },
});
```

---

## Agent Definitions

Define concrete agent implementations.

### GeneralAssistantAgent

```php
<?php

namespace App\Agents;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\UseTaskPlanning;
use Cognesy\Addons\Agent\Capabilities\UseWebSearch;
use Cognesy\Addons\Agent\Contracts\AgentContract;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeneralAssistantAgent implements AgentContract
{
    private ?CanHandleEvents $eventHandler = null;
    private ?callable $wiretapListener = null;
    private array $eventListeners = [];

    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: 'general-assistant',
            description: 'General-purpose assistant with file tools and web search',
            tools: NameList::fromArray(['read_file', 'write_file', 'web_search']),
            capabilities: NameList::fromArray(['file', 'web', 'tasks']),
        );
    }

    public function build(): Agent
    {
        $agent = AgentBuilder::base()
            ->withCapability(new UseFileTools(storage_path('assistant')))
            ->withCapability(new UseWebSearch())
            ->withCapability(new UseTaskPlanning())
            ->withMaxSteps(20)
            ->build();

        // Attach event handlers
        if ($this->eventHandler) {
            $agent->withEventHandler($this->eventHandler);
        }

        if ($this->wiretapListener) {
            $agent->wiretap($this->wiretapListener);
        }

        foreach ($this->eventListeners as $class => $listener) {
            $agent->onEvent($class, $listener);
        }

        return $agent;
    }

    public function run(AgentState $state): AgentState
    {
        return $this->build()->finalStep($state);
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self
    {
        $clone = clone $this;
        $clone->eventHandler = $events;
        return $clone;
    }

    public function wiretap(?callable $listener): self
    {
        $clone = clone $this;
        $clone->wiretapListener = $listener;
        return $clone;
    }

    public function onEvent(string $class, ?callable $listener): self
    {
        $clone = clone $this;
        $clone->eventListeners[$class] = $listener;
        return $clone;
    }

    public function serializeConfig(): array
    {
        return [
            'workspace' => storage_path('assistant'),
            'max_steps' => 20,
        ];
    }

    public static function fromConfig(array $config): Result
    {
        return Result::ok(new self());
    }
}
```

### Register Agent in Registry

```php
<?php

namespace App\Providers;

use App\Agents\GeneralAssistantAgent;
use Cognesy\Addons\Agent\Registry\AgentContractRegistry;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentContractRegistry::class, function () {
            $registry = new AgentContractRegistry();

            // Register available agents
            $registry->register('general-assistant', new GeneralAssistantAgent());
            // Add more agents as needed

            return $registry;
        });
    }
}
```

---

## Configuration

### Broadcasting Configuration

```php
// config/broadcasting.php

'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
    ],
],
```

### Queue Configuration

```php
// config/queue.php

'connections' => [
    'agents' => [
        'driver' => 'database', // or 'redis', 'sqs'
        'table' => 'jobs',
        'queue' => 'agents',
        'retry_after' => 300,
        'after_commit' => false,
    ],
],
```

---

## Deployment Checklist

### Database

- [ ] Run migrations for all tables
- [ ] Add indexes for performance
- [ ] Configure JSON column support

### Queue Workers

- [ ] Start dedicated agent queue: `php artisan queue:work --queue=agents`
- [ ] Configure supervisor for production
- [ ] Set appropriate timeout and retry settings

### Broadcasting

- [ ] Configure Pusher/Redis for Laravel Echo
- [ ] Test WebSocket connections
- [ ] Set up private channel authorization

### Agent Registry

- [ ] Register all agent implementations
- [ ] Test agent instantiation
- [ ] Verify AgentContract compliance

### Frontend

- [ ] Install Laravel Echo client
- [ ] Configure WebSocket connection
- [ ] Implement status update UI
- [ ] Test real-time event streaming

### Monitoring

- [ ] Add logging for agent executions
- [ ] Monitor queue depth and processing time
- [ ] Track failed jobs
- [ ] Set up alerts for errors

---

## Usage Flow Example

### 1. User Creates Session

```http
POST /api/assistant/sessions
{
  "agent_name": "general-assistant",
  "title": "Help with coding"
}

Response:
{
  "data": {
    "id": "uuid-session-1",
    "agent_name": "general-assistant",
    "status": "active",
    "created_at": "2026-01-06T10:00:00Z"
  }
}
```

### 2. User Sends Message

```http
POST /api/assistant/sessions/uuid-session-1/messages
{
  "content": "Can you help me refactor this function?"
}

Response (immediate):
{
  "data": {
    "id": 123,
    "role": "assistant",
    "content": "",
    "status": "pending",
    "created_at": "2026-01-06T10:00:01Z"
  }
}
```

### 3. Real-time Status Updates (WebSocket)

```javascript
// Client receives stream of events:

Event: agent.status.updated
{
  "event_type": "thinking",
  "message": "Analyzing your request...",
  "timestamp": "2026-01-06T10:00:02Z"
}

Event: agent.status.updated
{
  "event_type": "tool_call",
  "message": "Reading file contents...",
  "data": { "tool": "read_file", "args": {"path": "..."} },
  "timestamp": "2026-01-06T10:00:03Z"
}

Event: agent.status.updated
{
  "event_type": "tool_result",
  "message": "File read successfully",
  "timestamp": "2026-01-06T10:00:04Z"
}

Event: agent.status.updated
{
  "event_type": "step_complete",
  "message": "Completed step 1 of 3",
  "timestamp": "2026-01-06T10:00:05Z"
}

Event: agent.message.completed
{
  "message_id": 123,
  "response": "I've analyzed your function. Here are my suggestions...",
  "timestamp": "2026-01-06T10:00:15Z"
}
```

### 4. User Continues Conversation

Next message automatically uses same agent instance and state:

```http
POST /api/assistant/sessions/uuid-session-1/messages
{
  "content": "Can you also add error handling?"
}

# Agent has full context from previous exchange
```

---

## Benefits of This Architecture

### ✅ Deterministic Agent Reconstruction
- Agent instances recreated from `agent_name` + `agent_config`
- No serialized closures or builder state
- Works seamlessly with Laravel queue workers

### ✅ Real-time User Feedback
- Status updates stream via WebSocket as agent works
- Users see "thinking", "using tools", "completed step X"
- Much better UX than waiting with no feedback

### ✅ State Continuity
- AgentState persisted between messages
- Conversation context maintained
- Agent "remembers" previous exchanges in session

### ✅ Async Processing
- Non-blocking message handling
- Queue workers handle compute-intensive agent execution
- Web requests return immediately

### ✅ Event-Driven Architecture
- Clean separation via Laravel events
- Easy to add logging, analytics, debugging
- Extensible for future features

### ✅ Scalability
- Horizontal scaling via multiple queue workers
- Stateless workers (all state in database)
- Can add Horizon for monitoring and optimization

---

## Open Questions for Team

1. **Agent Registry Storage**: Should we persist agent definitions in database or keep them code-based?

2. **Context Window Management**: How to handle conversation history that exceeds LLM context window?

3. **Tool Authorization**: Should tools have per-user permission checks?

4. **Cost Control**: Rate limiting, token budgets per session/user?

5. **State Snapshot Size**: Binary serialization vs JSON for AgentState?

6. **Multi-tenancy**: Team-level agent configurations?

7. **Agent Versioning**: How to handle agent definition changes for in-flight sessions?

8. **Error Recovery**: Automatic retry logic for transient failures?

9. **Broadcasting Backend**: Pusher vs Redis vs custom WebSocket server?

10. **Event Granularity**: How detailed should status updates be? Every tool call or just step summaries?

---

## Next Steps

### Phase 1: Foundation (Week 1)
- [ ] Database migrations
- [ ] Eloquent models with relationships
- [ ] AgentExecutionService implementation
- [ ] Basic agent definition (GeneralAssistantAgent)
- [ ] Agent registry setup

### Phase 2: Queue Integration (Week 1-2)
- [ ] ProcessAgentMessage job
- [ ] Event handlers for state persistence
- [ ] Error handling and retry logic
- [ ] Queue worker configuration

### Phase 3: API Layer (Week 2)
- [ ] API routes and controllers
- [ ] Request validation
- [ ] Resource transformers
- [ ] Authorization policies

### Phase 4: Real-time Events (Week 2-3)
- [ ] Laravel Echo setup
- [ ] Event broadcasting configuration
- [ ] AgentStatusUpdated event
- [ ] AgentMessageCompleted event

### Phase 5: Frontend Integration (Week 3-4)
- [ ] Vue/React component
- [ ] WebSocket connection
- [ ] Status update UI
- [ ] Message rendering

### Phase 6: Testing & Optimization (Week 4)
- [ ] Unit tests for services
- [ ] Integration tests for job processing
- [ ] Load testing queue workers
- [ ] Performance optimization

---

## References

- **Agent Contract Spec**: `research/2026-01-06-agent-contract.md`
- **Laravel Integration Guide**: `research/2026-01-06-agents-in-laravel-app.md`
- **Laravel Echo Docs**: https://laravel.com/docs/broadcasting
- **Laravel Horizon**: https://laravel.com/docs/horizon
- **instructor-php Agent System**: `packages/addons/src/Agent/`
