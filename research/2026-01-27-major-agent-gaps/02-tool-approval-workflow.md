# PRD: Tool Approval Workflow

**Priority**: P0
**Impact**: High
**Effort**: Low
**Status**: Proposed

## Problem Statement

instructor-php has no built-in mechanism for requiring user approval before executing sensitive tool operations. Developers must implement custom observer patterns to achieve human-in-the-loop workflows, leading to inconsistent implementations and potential security gaps.

## Current State

```php
// Current: Must implement manually via observer
class ApprovalObserver extends PassThroughObserver {
    private PendingApprovals $pending;

    public function beforeToolUse(ToolCall $call, AgentState $state): ToolUseDecision {
        if ($this->requiresApproval($call)) {
            $this->pending->add($call);
            return ToolUseDecision::block($call, 'Awaiting approval');
        }
        return ToolUseDecision::allow($call);
    }

    private function requiresApproval(ToolCall $call): bool {
        // Custom logic - not standardized
        return in_array($call->name(), ['delete_file', 'send_email', 'execute_sql']);
    }
}
```

**Limitations**:
1. No standard way to mark tools as requiring approval
2. No built-in pause/resume mechanism
3. Approval state not serializable for async workflows
4. No UI hints or metadata for approval prompts

## Proposed Solution

### API Design

```php
interface ToolInterface {
    // Existing methods...

    /**
     * Whether this tool requires user approval before execution.
     *
     * @return bool|callable(mixed $input, ApprovalContext $context): bool
     */
    public function needsApproval(): bool|callable;
}

class ApprovalContext {
    public function __construct(
        public readonly string $toolCallId,
        public readonly AgentState $state,
        public readonly Messages $messages,
    ) {}
}

// Tool definition with approval
class DeleteFileTool implements ToolInterface {
    public function needsApproval(): bool|callable {
        return true; // Always requires approval
    }
}

class WriteFileTool implements ToolInterface {
    public function needsApproval(): bool|callable {
        // Conditional approval
        return function (array $input, ApprovalContext $context): bool {
            $path = $input['path'] ?? '';
            // Require approval for system files
            return str_starts_with($path, '/etc/') || str_starts_with($path, '/system/');
        };
    }
}
```

### Approval Request/Response

```php
class ApprovalRequest {
    public function __construct(
        public readonly string $approvalId,
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly array $toolArgs,
        public readonly string $description,
        public readonly DateTimeImmutable $requestedAt,
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function toArray(): array { ... }
    public static function fromArray(array $data): self { ... }
}

class ApprovalResponse {
    public function __construct(
        public readonly string $approvalId,
        public readonly bool $approved,
        public readonly ?string $reason = null,
        public readonly ?string $approvedBy = null,
        public readonly DateTimeImmutable $respondedAt = new DateTimeImmutable(),
    ) {}
}

enum ApprovalState: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Expired = 'expired';
}
```

### Agent State Integration

```php
class AgentState {
    // New methods
    public function pendingApprovals(): ApprovalRequests { ... }
    public function hasPendingApprovals(): bool { ... }
    public function withApprovalResponse(ApprovalResponse $response): self { ... }
    public function withApprovalRequest(ApprovalRequest $request): self { ... }
}

// New stop reason
enum StopReason: string {
    // Existing...
    case AwaitingApproval = 'awaiting_approval';
}
```

### Execution Flow

```php
// Automatic pause when approval needed
$state = $agent->execute($initialState);

if ($state->hasPendingApprovals()) {
    foreach ($state->pendingApprovals() as $request) {
        // Show to user
        echo "Approve {$request->toolName}({$request->toolArgs})? ";

        // Get decision (e.g., from UI, API, etc.)
        $approved = $this->getUserDecision($request);

        // Continue with response
        $state = $state->withApprovalResponse(new ApprovalResponse(
            approvalId: $request->approvalId,
            approved: $approved,
            reason: $approved ? null : 'User denied',
            approvedBy: $currentUser->id,
        ));
    }

    // Resume execution
    $state = $agent->execute($state);
}
```

### Async/Webhook Workflow

```php
// Step 1: Start execution (may pause for approval)
$state = $agent->execute($initialState);

if ($state->stopReason() === StopReason::AwaitingApproval) {
    // Serialize state for later
    $serialized = $state->toArray();
    $this->cache->set("agent:{$state->agentId()}", $serialized, ttl: 3600);

    // Send webhook/notification
    $this->notifyApprovalNeeded($state->pendingApprovals());

    return new JsonResponse([
        'status' => 'awaiting_approval',
        'agentId' => $state->agentId(),
        'approvals' => $state->pendingApprovals()->toArray(),
    ]);
}

// Step 2: Handle approval webhook
public function handleApprovalWebhook(Request $request): Response {
    $agentId = $request->get('agentId');
    $approvalId = $request->get('approvalId');
    $approved = $request->get('approved');

    // Restore state
    $serialized = $this->cache->get("agent:{$agentId}");
    $state = AgentState::fromArray($serialized);

    // Apply response
    $state = $state->withApprovalResponse(new ApprovalResponse(
        approvalId: $approvalId,
        approved: $approved,
    ));

    // Resume
    $finalState = $this->agent->execute($state);

    return new JsonResponse(['status' => $finalState->status()->value]);
}
```

## How Other Libraries Implement This

### Vercel AI SDK

**Location**: `packages/ai/src/generate-text/generate-text.ts`

```typescript
// Tool definition with needsApproval
const deleteTool = tool({
    description: 'Delete a file',
    parameters: z.object({ path: z.string() }),
    needsApproval: true,  // Always requires
    execute: async ({ path }) => fs.unlink(path),
});

// Conditional approval
const writeTool = tool({
    description: 'Write to file',
    parameters: z.object({ path: z.string(), content: z.string() }),
    needsApproval: async (input, { messages }) => {
        return input.path.startsWith('/etc/');
    },
    execute: async ({ path, content }) => fs.writeFile(path, content),
});
```

**Execution Flow**:
```typescript
const result = await generateText({
    model: openai('gpt-4o'),
    tools: { delete: deleteTool },
    prompt: 'Delete file.txt',
});

// Check for pending approvals
const pendingApprovals = result.content.filter(
    part => part.type === 'tool-approval-request'
);

if (pendingApprovals.length > 0) {
    // Get user decision
    const approved = await getUserApproval(pendingApprovals[0]);

    // Continue with approval
    const continueResult = await generateText({
        model: openai('gpt-4o'),
        tools,
        messages: [
            ...result.response.messages,
            {
                role: 'tool',
                content: [{
                    type: 'tool-approval-response',
                    approvalId: pendingApprovals[0].approvalId,
                    approved,
                }],
            },
        ],
    });
}
```

**Key Implementation Details**:
1. `needsApproval` can be boolean or async function
2. Approval requests are content parts in response
3. Responses sent as tool messages
4. Approval state tracked via `approvalId`

### UI State Representation

```typescript
type UIToolInvocation = {
    toolCallId: string;
    title?: string;
} & (
    | { state: 'input-streaming'; input: DeepPartial<INPUT> }
    | { state: 'input-available'; input: INPUT }
    | { state: 'approval-requested'; input: INPUT; approval: { id: string } }
    | { state: 'approval-responded'; input: INPUT; approval: { id: string; approved: boolean } }
    | { state: 'output-available'; input: INPUT; output: OUTPUT }
    | { state: 'output-denied'; input: INPUT; approval: { id: string; approved: false } }
);
```

### LangGraph (LangChain)

**Location**: `langgraph/prebuilt/tool_node.py`

```python
# Human-in-the-loop via interrupt
from langgraph.prebuilt import ToolNode, tools_condition
from langgraph.checkpoint import MemorySaver

def should_continue(state):
    if needs_human_approval(state):
        return "human_approval"
    return tools_condition(state)

graph = StateGraph(State)
graph.add_node("agent", agent)
graph.add_node("tools", ToolNode(tools))
graph.add_node("human_approval", human_approval_node)

graph.add_conditional_edges("agent", should_continue)
graph.add_edge("human_approval", "agent")

# Execution with checkpointing
app = graph.compile(checkpointer=MemorySaver())
result = app.invoke({"messages": [...]}, config={"thread_id": "1"})

# Resume after approval
app.update_state(config, {"approved": True})
result = app.invoke(None, config)  # Resume from checkpoint
```

**Key Implementation Details**:
1. Interrupt mechanism via graph nodes
2. Checkpointing for state persistence
3. `update_state` for injecting approval
4. Thread-based execution tracking

## Implementation Considerations

### ToolInterface Changes

```php
interface ToolInterface {
    // Add to existing interface
    public function needsApproval(): bool|callable;

    // Optional: approval prompt customization
    public function approvalPrompt(array $input): string;
}

// Default implementation in base class
abstract class BaseTool implements ToolInterface {
    public function needsApproval(): bool|callable {
        return false;  // Default: no approval needed
    }

    public function approvalPrompt(array $input): string {
        return sprintf(
            'Execute %s with arguments: %s?',
            $this->name(),
            json_encode($input)
        );
    }
}
```

### ToolExecutor Changes

```php
class ToolExecutor implements CanExecuteToolCalls {
    public function useTool(ToolCall $toolCall, AgentState $state): ToolExecution|ApprovalRequest {
        $tool = $this->tools->get($toolCall->name());

        // Check approval requirement
        $needsApproval = $this->evaluateApprovalRequirement($tool, $toolCall, $state);

        if ($needsApproval) {
            // Check for existing approval
            $existingApproval = $state->approvalFor($toolCall->id());

            if ($existingApproval === null) {
                // Request approval
                return new ApprovalRequest(
                    approvalId: Uuid::uuid4(),
                    toolCallId: $toolCall->id(),
                    toolName: $toolCall->name(),
                    toolArgs: $toolCall->args(),
                    description: $tool->approvalPrompt($toolCall->args()),
                );
            }

            if (!$existingApproval->approved) {
                // Approval denied
                return new ToolExecution(
                    toolCall: $toolCall,
                    result: Result::failure(new ToolCallDeniedException(
                        $toolCall->name(),
                        $existingApproval->reason ?? 'User denied'
                    )),
                    ...
                );
            }
        }

        // Execute normally
        return $this->executeDirectly($toolCall, $state);
    }
}
```

### Continuation Criteria

```php
class ApprovalPendingCheck implements CanEvaluateContinuation {
    public function evaluate(AgentState $state): ContinuationEvaluation {
        if ($state->hasPendingApprovals()) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::ForbidContinuation,
                reason: 'Awaiting approval for tool calls',
                stopReason: StopReason::AwaitingApproval,
            );
        }

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: ContinuationDecision::AllowContinuation,
            reason: 'No pending approvals',
        );
    }
}
```

## Migration Path

1. **Phase 1**: Add `needsApproval()` to ToolInterface with default `false`
2. **Phase 2**: Add ApprovalRequest/Response classes
3. **Phase 3**: Update AgentState to track approvals
4. **Phase 4**: Modify ToolExecutor to check approvals
5. **Phase 5**: Add ApprovalPendingCheck criterion
6. **Phase 6**: Add serialization for async workflows

## Success Metrics

- [ ] Tools can declaratively require approval
- [ ] Conditional approval based on input
- [ ] Agent pauses when approval needed
- [ ] State serializable for async approval
- [ ] Approval timeout/expiration support
- [ ] Existing tools work without changes

## Open Questions

1. Should denied approvals retry or fail permanently?
2. How to handle approval timeout during execution?
3. Should we support batch approval (approve all pending)?
4. How to integrate with existing observer pattern?
