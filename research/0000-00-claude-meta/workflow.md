Prompts don't scale. MCPs don't scale. Hooks do.
Suggestion
TLDR; click for the post contains only actions

Background: 6 years building trading systems. HFT bot made me financially free. Now I build AI tools the same way I build trading systems - conditional, not predictive.

I've been building AI tools for myself - VSCode extensions, Telegram bots, macOS apps, MCP servers. All different domains, same discovery.

What i disocver is Long prompts don't work. Context shifts, prompt breaks. I tried everything, all takes more time than it returned.

I stopped writing prompts. Now I write hooks (triggers when ai read file/path, or write specifit context that i catch with regex or custom logic)

  ┌─────────────────────────────────────────────────────────────────┐
  │                      CLAUDE RULES SYSTEM                        │
  └─────────────────────────────────────────────────────────────────┘

  FLOW
  ────
  Claude event (Write/Edit/Bash tool)
         ↓
  PreToolUse hook (stdin JSON)
         ↓
  manifest.ts (all rules array)
         ↓
  Each rule.decision({ toolName, toolInput, ... })
         ↓
  rule.check({ content/command/... })
         ↓
  { shouldBlock: true/false, decision?, reason? }
         ↓
  Collect violations → strictest decision
         ↓
  stdout JSON (deny/ask/allow + reason)
Same logic as trading. You don't predict "this will happen." You set conditions - "if this, then that." Technical analysis alone isn't the answer, but conditional execution might be.

Prompts bloat context. You add rules, AI gets confused. More instructions, worse output. Doesn't scale.

Hooks only fire when AI makes a mistake. No mistake, no intervention. Context stays clean. It's something like Progressive disclosure, learn when you experienced.

Example: I use MVI architecture. Every model must go through Intent, not direct access. I don't write "always use Intent" in a prompt. I write a hook - if AI places code that accesses model directly without Intent, hook blocks it. Won't let it write. Here is the MVI visualization from AI

  ┌─────────────────────────────────────────────────────────────────┐
  │                 MVI UNIDIRECTIONAL FLOW                         │
  ├─────────────────────────────────────────────────────────────────┤
  │                                                                 │
  │  LEGAL FLOW:                                                    │
  │  ─────────────────────                                          │
  │                                                                 │
  │  Transport ──► Intent ──► Middleware ──► dispatch() ──► Reducer│
  │  (Menu/HTTP)                (side fx)                    (pure) │
  │                                │                           │    │
  │                                ▼                           ▼    │
  │                           Capability                    State   │
  │                           (external)                  (immut)   │
  │                                                           │    │
  │                                                           ▼    │
  │                                                     Derived    │
  │                                                   (ContextKeys)│
  │                                                           │    │
  │                                                           ▼    │
  │                                                        View    │
  │                                                                 │
  └─────────────────────────────────────────────────────────────────┘
Another one: Every file needs purpose documented at the top that contains OUTCOME, PATTERN/STRATEGY, CONSTRAINTS. AI doesn't write the purpose comment? Hook blocks. Forces the action.

This can scale to thousands of rules. Prompt can't hold thousands of rules. Hook system can - each rule only activates on its condition.

Now I don't say "explain." I say "show / point." ASCII diagrams, visualizations. AI executes while I learn from what it's showing me. Two things at once. For example; AI shows state diagrams to teach me how system acts. Here is some visualization it made

   @MainActor                         @MLActor                      
  TranscriptionQueue            WhisperKitBatch               
  ┌──────────────┐              ┌──────────────┐              
  │ enqueue()    │              │              │              
  │ processNext()│──await──────►│ transcribe() │              
  │ handle*()    │◄─────────────│              │              
  └──────────────┘  Result      └──────────────┘              
        │                             │                       
        ▼                             ▼                       
  UI responsive ✓              Neural Engine ✓           
I stopped writing docs. Docs rot, need maintenance, get outdated. Code breaks visibly - you see it, you fix it. Self-documenting.

I keep repetitive code now. Sounds wrong but here's why - repeated code means visible pattern. AI sees it, extracts it. Hidden abstractions hide patterns. AI can't learn what it can't see.

When I build something new, I say "look at my Telegram bot, look at my VSCode extension, build it like that." No explanation. Just reference. Works because I built those, I understand them, I can direct the AI.

macOS (Swift - which i dont know, but learned by doing app with ai)
if you wonder the app i made, comment
  ─────────────
  Sources/Contexts/{Feature}/
  ├── {Name}Intent.swift       → routing (enum cases)
  ├── {Name}State.swift        → u/Published singleton
  ├── {Name}Capability.swift   → external API
  ├── {Name}Config.swift       → configuration
  ├── {Name}HTTP.swift         → HTTP endpoints
  ├── {Name}Lifecycle.swift    → initialization
  ├── {Name}Menu.swift         → UI menu
  └── CLAUDE.md                → docs

VSCode Extension (TypeScript)
  ──────────────────────────────
  contexts/{feature}/
  ├── {name}-intent.ts         → command routing
  ├── {name}-state.ts          → state management
  ├── {name}-capability.ts     → VSCode API
  ├── {name}-lifecycle.ts      → activation
  └── CLAUDE.md                → docs

Telegram Bot (TypeScript)
  ──────────────────────────
  src/telegram/contexts/{feature}/
  ├── {name}-{action}.ts       → feature handlers
  └── CLAUDE.md                → docs

  ═══════════════════════════════════════════════════════════════════
    UNIFIED PATTERN (macOS, vscode extension, telegram, hammerspoon)
  ═══════════════════════════════════════════════════════════════════

  Contexts/{Feature}/
  ├── Intent      → routing
  ├── State       → data
  ├── Capability  → external dependency
  ├── Config      → settings
  ├── Transport   → HTTP/Menu/Command
  ├── Lifecycle   → initialization
  └── CLAUDE.md   → architecture doc (outcome, constraints, patterns...)

  Feature = Vertical Slice (isolated, self-contained)
  Context Boundary = Capability Isolation
Btw, I also have hook for writing CLAUDE.md like

`CLAUDE.md structure violation

Missing sections:
${missing.map(s => `• ${s}`).join('\n')}


CONSTRAINT: CLAUDE.md must contain OUTCOME + PATTERN + CONSTRAINT + DEPENDENCY

PATTERN:
# {filepath}


## OUTCOME [what this produces]
...


## PATTERN [correlation/architecture]
...


## CONSTRAINT [syntactic validation rules]
...


## DEPENDENCY [external dependencies]
...`
Best practice doesn't matter as you think. My practice + "little best" matters. You can't steer what you don't understand.

What survives: generic rules, repeated patterns. Specific rules die - they don't repeat, they mislead. The rules that accumulate reflect your actual coding style. Your if-else emerges over time.

Cross-domain transfer works. Pattern I learned in Swift, I convert to TypeScript rule. "Let's extract a generic rule here." Write once, applies everywhere.

┌─────────────────────────────────────────────────────────────────┐
│          SWIFT × TYPESCRIPT RULES CROSS-LANGUAGE PATTERNS        │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 1. FILE HEADER STRUCTURE                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Swift:                    TypeScript:                          │
│  // MARK: - Section       // MARK: - Section                   │
│  // OUTCOME: ...          /**                                   │
│  // PATTERN: ...           * OUTCOME: ...                       │
│  // CONSTRAINT: ...         * PATTERN: ...                       │
│                            * CONSTRAINT: ...                    │
│                            */                                    │
│                                                                 │
│  PATTERN: Documentation structure mandatory                     │
│  - Swift: Comment-based (// MARK, // OUTCOME)                 │
│  - TypeScript: JSDoc + MARK comments                            │
│  CONSTRAINT: File-level validation (Write tool only)            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 2. STATE MACHINE (Invalid State Impossible)                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Swift:                    TypeScript:                          │
│  enum AudioState {         type State =                          │
│    case idle                 | { status: 'idle' }                 │
│    case recording            | { status: 'loading' }             │
│  }                           | { status: 'done'; data: T }       │
│                                                                 │
│  FORBIDDEN:                 FORBIDDEN:                          │
│  var isRecording: Bool     type State = {                        │
│  var isPaused: Bool          isLoading: boolean                  │
│                              isError: boolean                    │
│                              }                                   │
│                                                                 │
│  PATTERN: Multiple boolean state vars → discriminated union    │
│  CONSTRAINT: Compile-time exhaustive handling                    │
│  - Swift: enum-exhaustive.ts                                    │
│  - TypeScript: state-machine.ts                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 3. ACTION PATTERN (Exhaustive, Finite Set)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Swift:                    TypeScript:                          │
│  enum DictationAction {    type DictationAction =                │
│    case toggle              | { type: 'toggle' }                │
│    case setRecording(Bool)  | { type: 'set'; value: boolean }    │
│    case addEntry(Entry)     | { type: 'add'; entry: Entry }     │
│  }                                                                 │
│                                                                 │
│  FORBIDDEN:                 FORBIDDEN:                          │
│  class DictationAction     class DictationAction { ... }         │
│  struct DictationAction    interface DictationAction { ... }      │
│                                                                 │
│  PATTERN: Action = enum/discriminated union (not class/struct)  │
│  CONSTRAINT: *Action.swift / *-action.ts file validation         │
│  - Swift: action-enum.ts                                        │
│  - TypeScript: action-type.ts                                   │
└─────────────────────────────────────────────────────────────────┘
Prompts = upfront load, context bloat, doesn't scale. Hooks = on-demand, signal-based, scales infinitely.