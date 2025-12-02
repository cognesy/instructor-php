# Task Master Cheatsheet

## Setup (One-time)
```bash
task-master init -y --no-aliases --name="project"  # Minimal setup, creates .taskmaster/
task-master models --setup                          # Configure AI models interactively
# Add to .env: ANTHROPIC_API_KEY=sk-... OPENAI_API_KEY=sk-...
```
**Why:** Enable AI task generation without cluttering codebase

## Project Planning
```bash
task-master parse-prd --input=requirements.txt --num-tasks=20  # PRD → actionable tasks
task-master analyze-complexity --threshold=5                   # Find tasks needing breakdown
task-master expand --id=3 --num=5 --research                  # Complex task → subtasks
task-master expand --all --force                              # Expand all pending tasks
```
**Why:** Transform vague requirements into concrete implementation steps

## Daily Workflow
```bash
task-master next                                    # What to work on (respects dependencies)
task-master show 3                                  # Task details before starting
task-master set-status --id=3 --status=in-progress  # Mark as working
task-master set-status --id=3 --status=done        # Mark complete
task-master list --status=in-progress              # Current work
```
**Why:** Maintain focus, track progress for AI context

## AI Agent Integration
```bash
# Tell Claude/Codex: "I'm working on task #3 from task-master"
task-master show 3 | pbcopy                         # Copy task to clipboard for agent
task-master research "implement Option monad" \
  -f packages/utils/src/ -c "PHP 8.2 strict types"  # Context-aware research
task-master update-task --id=3 \
  --prompt="Add error handling for network failures" # Refine task with discoveries
```
**Why:** Give AI agents structured context for better assistance

## Task Management
```bash
task-master add-task --prompt="Add Redis caching"   # Quick task addition
task-master add-subtask --parent=3 --title="Write tests"
task-master add-dependency --id=5 --depends-on=3    # Task 5 needs task 3 first
task-master validate-dependencies                   # Check for circular deps
task-master remove-task --id=7 -y                  # Delete task
```
**Why:** Maintain clean task graph as project evolves

## Multi-Context Projects (Tags)
```bash
task-master add-tag refactor -d "Q1 refactoring"   # Create context
task-master use-tag refactor                       # Switch context
task-master copy-tag main refactor                 # Clone tasks to new context
task-master tags --show-metadata                   # List all contexts
```
**Why:** Separate experiments/refactors from main development

## Progress Tracking
```bash
task-master list --with-subtasks                   # Full hierarchy view
task-master complexity-report                      # Workload analysis
task-master sync-readme --with-subtasks            # Export to README.md
git add README.md && git commit -m "Update tasks"  # Track in git
```
**Why:** Stakeholder visibility, sprint planning

## Research & Discovery
```bash
task-master research "best practice for DDD aggregates" \
  -i=3,4,5 -f=packages/domain/ -d=detailed \
  -s=research/ddd-aggregates.md                    # Save research
task-master research "performance bottlenecks" \
  --tree -c="high traffic scenarios"               # Hierarchical analysis
```
**Why:** Leverage AI for technical decisions with project awareness

## Quick Commands
```bash
alias tm='task-master'                             # Shell alias
tm n       # next
tm l       # list
tm s 3 d   # set-status --id=3 --status=done
```

## Status Values
`pending` | `in-progress` | `done` | `review` | `deferred` | `cancelled`

## Key Benefits Summary
- **PRD→Tasks:** AI decomposes requirements automatically
- **Agent Context:** Tasks become shared language with AI
- **Research:** Project-aware technical guidance
- **Dependencies:** Automatic work ordering
- **No Lock-in:** Just JSON files in .taskmaster/

## DIRECTORIES / FILES

- `.taskmaster/` - All config and data
  - `tasks.json` - Main task storage
  - `models.json` - AI model settings
  - `tags/` - Context-specific task files\
- .env - API keys and env vars
- ./scripts/ - Seems like taskmaster expects PRD files here
- 