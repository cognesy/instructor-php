# Namespace Separation Plan

## Goal

Separate `packages/agents/src/Agent` into:
1. **Core** - Shared building blocks used by multiple agent implementations
2. **Agent** - The base agent implementation
3. **AgentHooks** - Hook system (opt-in capability)

## Proposed Structure

```
packages/agents/src/
├── Core/                          # Shared building blocks
│   ├── Collections/               # Collection classes
│   ├── Contracts/                 # Interfaces
│   ├── Continuation/              # Continuation logic
│   ├── Data/                      # Data structures
│   ├── Enums/                     # Enums
│   ├── ErrorHandling/             # Error handling
│   ├── Events/                    # Event classes
│   ├── Exceptions/                # Exceptions
│   ├── MessageCompilation/        # Message compilation
│   └── Tools/                     # Tool base classes
│
├── Agent/                         # Base agent implementation
│   ├── Agent.php                  # Main class
│   ├── ToolExecutor.php           # Tool execution
│   └── StateProcessing/           # State processors
│       └── Processors/
│
├── AgentHooks/                    # Hook system (opt-in)
│   ├── Contracts/
│   ├── Data/
│   ├── Enums/
│   ├── Hooks/
│   ├── Matchers/
│   ├── Stack/
│   └── Adapters/
│
└── PipelineAgent/                 # Pipeline-based agent (already done)
    └── Handlers/
```

## File Mapping

### Core (Cognesy\Agents\Core)

| Current Location | New Location |
|-----------------|--------------|
| Agent/Collections/* | Core/Collections/* |
| Agent/Contracts/* | Core/Contracts/* |
| Agent/Continuation/* | Core/Continuation/* |
| Agent/Data/* | Core/Data/* |
| Agent/Enums/* | Core/Enums/* |
| Agent/ErrorHandling/* | Core/ErrorHandling/* |
| Agent/Events/* | Core/Events/* |
| Agent/Exceptions/* | Core/Exceptions/* |
| Agent/MessageCompilation/* | Core/MessageCompilation/* |
| Agent/Tools/* | Core/Tools/* |

### Agent (Cognesy\Agents\Agent)

| Current Location | New Location |
|-----------------|--------------|
| Agent/Agent.php | Agent/Agent.php |
| Agent/ToolExecutor.php | Agent/ToolExecutor.php |
| Agent/StateProcessing/* | Agent/StateProcessing/* |

### AgentHooks (Cognesy\Agents\AgentHooks)

| Current Location | New Location |
|-----------------|--------------|
| Agent/Hooks/Contracts/* | AgentHooks/Contracts/* |
| Agent/Hooks/Data/* | AgentHooks/Data/* |
| Agent/Hooks/Enums/* | AgentHooks/Enums/* |
| Agent/Hooks/Hooks/* | AgentHooks/Hooks/* |
| Agent/Hooks/Matchers/* | AgentHooks/Matchers/* |
| Agent/Hooks/Stack/* | AgentHooks/Stack/* |
| Agent/Hooks/Adapters/* | AgentHooks/Adapters/* |

## Namespace Changes

| Old Namespace | New Namespace |
|--------------|---------------|
| `Cognesy\Agents\Agent\Collections` | `Cognesy\Agents\Core\Collections` |
| `Cognesy\Agents\Agent\Contracts` | `Cognesy\Agents\Core\Contracts` |
| `Cognesy\Agents\Agent\Continuation` | `Cognesy\Agents\Core\Continuation` |
| `Cognesy\Agents\Agent\Data` | `Cognesy\Agents\Core\Data` |
| `Cognesy\Agents\Agent\Enums` | `Cognesy\Agents\Core\Enums` |
| `Cognesy\Agents\Agent\ErrorHandling` | `Cognesy\Agents\Core\ErrorHandling` |
| `Cognesy\Agents\Agent\Events` | `Cognesy\Agents\Core\Events` |
| `Cognesy\Agents\Agent\Exceptions` | `Cognesy\Agents\Core\Exceptions` |
| `Cognesy\Agents\Agent\MessageCompilation` | `Cognesy\Agents\Core\MessageCompilation` |
| `Cognesy\Agents\Agent\Tools` | `Cognesy\Agents\Core\Tools` |
| `Cognesy\Agents\Agent\Hooks` | `Cognesy\Agents\AgentHooks` |
| `Cognesy\Agents\Agent\Hooks\*` | `Cognesy\Agents\AgentHooks\*` |

## Migration Steps

### Step 1: Create Core directory structure
```bash
mkdir -p packages/agents/src/Core/{Collections,Contracts,Continuation,Data,Enums,ErrorHandling,Events,Exceptions,MessageCompilation,Tools}
```

### Step 2: Move files to Core
Move all shared building blocks.

### Step 3: Create AgentHooks directory
```bash
mkdir -p packages/agents/src/AgentHooks/{Contracts,Data,Enums,Hooks,Matchers,Stack,Adapters}
```

### Step 4: Move Hooks to AgentHooks
Move entire Hooks system.

### Step 5: Update namespaces
Update all namespace declarations and use statements.

### Step 6: Update dependent code
- AgentBuilder
- Drivers
- PipelineAgent
- Tests

## Benefits

1. **Clear separation** - Core vs implementation vs optional features
2. **Easier to understand** - What's shared vs what's specific
3. **Opt-in hooks** - AgentHooks can be added via capability
4. **Multiple implementations** - Agent and PipelineAgent share Core
5. **Future extensibility** - New agent types can reuse Core

## Risk Mitigation

- Run PHPStan after each step
- Run tests after each step
- Keep old paths as aliases temporarily if needed
