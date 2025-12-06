TLDR - Prompts don't scale. MCPs don't scale. Hooks do.
Suggestion
This post contains only actions - for information refer to Original post • My personal story is Turkish • English

DON'T WRITE PROMPTS → WRITE HOOKS
Block AI when it makes mistake (not before)

Each rule = single condition: "if X, block"

Example: in MVI architecture, AI accesses model directly → force Intent

DON'T WRITE DOCS → ENFORCE HEADERS
Every file top or CLAUDE.md for folder:

// OUTCOME: What does it produce?
// PATTERN: What correlation?
// CONSTRAINT: What's forbidden?
// QUERY: Which questions it solves (optional)

NO EXPLANATION - NO REASONING - NO ANALOGY
DON'T ABSTRACT → REPEAT
Repeated code = AI can learn. USE boilerplate! - yes you read right

Hidden abstraction = AI can't see

DON'T EXPLAIN → REFERENCE
"Look at my Telegram bot, build like that"

[Copy macOS panel / website screenshot - or open source project link] -> "[paste] Design / build like this"

Code example > 1000 words explanation

DON'T USE BOOLEAN STATE → DISCRIMINATED UNION
✗ isLoading + isError + isDone
✓ { status: 'loading' } | { status: 'error'; msg } | { status: 'done'; data }
BE DETERMINISTIC, prefer FINITE SET
DON'T GO HORIZONTAL → VERTICAL SLICE
contexts/{feature}/
├── intent      (routing)
├── state       (data)
├── capability  (external)
└── CLAUDE.md   (rules)