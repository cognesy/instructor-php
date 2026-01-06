# Agent Deployment Research

Research documentation for deploying Instructor PHP agents in PHP framework applications.

## Documents

### [Laravel Agent Deployment](./laravel-agent-deployment.md)

Core patterns for embedding agents in Laravel:

- **Job-Based Execution** - Using Laravel queues with Horizon
- **Status Communication** - Database state, WebSocket broadcasting, SSE streaming
- **Logging** - Structured logs for admin visibility
- **Lifecycle Management** - Start, pause, resume, cancel, force-kill
- **Scaling** - Rate limiting, queue configuration, parallel execution
- **Event-Driven Awakening** - Webhook triggers, scheduled execution
- **Long-Running Jobs** - Chunked execution with checkpoints

### [Symfony Agent Deployment](./symfony-agent-deployment.md)

Core patterns for embedding agents in Symfony:

- **Messenger Integration** - Using Symfony Messenger for async jobs
- **Entity Design** - Doctrine ORM entities for execution state
- **Message Handlers** - `#[AsMessageHandler]` for agent execution
- **Mercure Updates** - Real-time status via Mercure hub
- **Worker Configuration** - supervisor/systemd setup
- **Rate Limiting** - symfony/rate-limiter integration
- **Event Triggering** - Webhook and scheduled execution
- **Chunked Execution** - Long-running jobs with checkpoints

### [Advanced Patterns](./advanced-patterns.md)

Enterprise-grade patterns (framework-agnostic):

- **Multi-Tenant Isolation** - Workspace isolation, sandboxed execution, disk quotas
- **Conversation Continuity** - Persistent conversations, message history
- **Agent Orchestration** - Multi-agent pipelines, sequential execution
- **Distributed Execution** - Redis coordination, lock management
- **Observability** - OpenTelemetry tracing, Prometheus metrics
- **Cost Tracking** - Token usage billing, budget enforcement

## Framework Comparison

| Aspect | Laravel | Symfony |
|--------|---------|---------|
| Async Jobs | Laravel Queue + Horizon | Symfony Messenger |
| ORM | Eloquent | Doctrine |
| Real-time | Laravel Echo + Pusher/Soketi | Mercure |
| Rate Limiting | RateLimiter facade | RateLimiterFactory |
| Worker Management | Horizon dashboard | supervisor/systemd |
| Scheduling | Task Scheduling | Symfony Scheduler |
| Events | Laravel Events | Messenger async dispatch |

## Quick Reference (Laravel)

### Starting an Agent

```php
$manager = app(AgentManager::class);
$execution = $manager->start(
    userId: $user->id,
    agentType: 'code-assistant',
    input: ['prompt' => 'Review this code for bugs'],
);
```

### Controlling Execution

```php
$manager->pause($execution);   // Pause at next checkpoint
$manager->resume($execution);  // Continue paused execution
$manager->cancel($execution);  // Graceful stop
$manager->forceKill($execution); // Immediate termination
```

### Listening for Updates

```javascript
// Frontend (Laravel Echo)
Echo.private(`agent.${executionId}`)
  .listen('.step.completed', (e) => console.log('Step:', e.step_number))
  .listen('.status.changed', (e) => console.log('Status:', e.status));
```

### Event-Triggered Execution

```php
// Start agent that waits for event
$trigger = app(AgentEventTrigger::class);
$execution = $trigger->startAwaitingEvent(
    userId: $user->id,
    agentType: 'research',
    input: ['prompt' => 'Analyze new data'],
    awaitEventType: 'data.uploaded',
);

// Later, trigger the event
$trigger->triggerEvent('data.uploaded', ['file_id' => 123]);
```

## Quick Reference (Symfony)

### Starting an Agent

```php
$manager = $container->get(AgentManager::class);
$execution = $manager->start(
    user: $user,
    agentType: 'code-assistant',
    input: ['prompt' => 'Review this code for bugs'],
);
```

### Controlling Execution

```php
$manager->pause($execution);   // Send pause signal
$manager->resume($execution);  // Continue paused execution
$manager->cancel($execution);  // Graceful stop
$manager->forceKill($execution); // Immediate termination
```

### Listening for Updates (Mercure)

```javascript
// Get Mercure token from API
const { token, hub_url, topic } = await fetch('/api/agents/{id}/mercure-token').then(r => r.json());

const url = new URL(hub_url);
url.searchParams.append('topic', topic);

const eventSource = new EventSource(url);
eventSource.onmessage = (e) => {
    const data = JSON.parse(e.data);
    console.log('Update:', data.type, data.status);
};
```

### Worker Commands

```bash
# Start workers (development)
php bin/console messenger:consume agents agents_long async -vv

# Production (supervisor)
supervisorctl start messenger:*
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Laravel Application                         │
├─────────────────────────────────────────────────────────────────┤
│  API Request → AgentManager → Queue Job → Agent Execution       │
│       ↓                                          ↓              │
│  Rate Limiter                              Event Broadcasting    │
│       ↓                                          ↓              │
│  Queue Dispatch                            WebSocket/SSE        │
│       ↓                                          ↓              │
│  Horizon Workers                           Admin Dashboard      │
│       ↓                                                         │
│  AgentExecutionService                                          │
│       ↓                                                         │
│  Instructor Agent (with checkpoints)                            │
│       ↓                                                         │
│  Database (state, logs, signals)                                │
└─────────────────────────────────────────────────────────────────┘
```

## Key Database Tables

| Table | Purpose |
|-------|---------|
| `agent_executions` | Execution state, input/output, checkpoints |
| `agent_logs` | Structured logs per execution |
| `agent_signals` | Control signals (pause, cancel, input) |
| `agent_conversations` | Multi-turn conversation history |
| `agent_pipelines` | Multi-agent orchestration |
| `token_usage_records` | Billing and cost tracking |

## Implementation Checklist

- [ ] Set up database migrations
- [ ] Configure Horizon queues
- [ ] Implement `AgentExecutionService`
- [ ] Set up WebSocket broadcasting (Pusher/Soketi)
- [ ] Create admin dashboard for monitoring
- [ ] Implement rate limiting per user tier
- [ ] Add scheduled cleanup jobs
- [ ] Set up stuck-job recovery
- [ ] Configure metrics collection (optional)
- [ ] Implement cost tracking (optional)

## Related Files

- `packages/addons/AGENT.md` - Agent system documentation
- `packages/addons/src/Agent/` - Agent implementation
