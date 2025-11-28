# Key Reference Files

## Monorepo Root

- **CONTRIBUTOR_GUIDE.md** - Development workflows, package management, and contribution guidelines
- **CONTENTS.md** - Project structure and package overview
- **README.md** - Main project documentation

## Individual Subpackages

Each package in `packages/` may contain:
- **README.md** - Package-specific documentation
- **OVERVIEW.md** - Package overview and architecture
- **INTERNALS.md** - Implementation details and internal structure
- **CHEATSHEET.md** - Quick reference for package usage

# Code Style

- Use strict types and type hints for arguments and return values everywhere
- Do not nest control structures (if, loops) beyond 1 level, exceptionally 2 levels
- Use `match` for complex conditionals - avoid `if`/`else if` chains or `switch` statements; avoid ternary operators
- Prefer immutable data structures and functional programming paradigms
- Avoid using arrays as collections - use dedicated collection classes
- Avoid exceptions for control flow - do not wrap everything in try/catch; either let exceptions bubble up or use monadic Cognesy\Utils\Result\Result for error handling if error needs to be handled on the same level
- Use namespaces and PSR-4 autoloading

# Design Principles

- Always start with extremely simple code, refactor when it is required - do not over-engineer solutions (YAGNI)
- Use DDD (Domain Driven Design) principles - aggregate roots, value objects, entities, repositories, services
- Use Clean Code and SOLID principles
- Use interfaces for contracts, avoid concrete class dependencies
- Early returns on errors, use monadic Cognesy\Utils\Result\Result for any complex error handling

# Tests and Quality

- Use Pest for testing
- Test only services and domain logic, not external dependencies or displayed information
- Before writing tests make sure that the code is easily testable - if not propose refactorings
- Use PHPStan and Psalm for static analysis

# Development Tools

- ast-grep - import documentation from: @./notes/tools/AST_GREP.md
- task-master - import documentation from: @./docs-internal/taskmaster.md
- bd/bv (beads) - issue tracking system, see: @./docs-internal/bd-bv/bd_bv_cheatsheet.md

# Issue Tracking with bd/bv

This project uses **bd** (beads) for issue tracking and **bv** (beads viewer) for visualization and graph analysis.

## Quick Reference

### Finding Work
```bash
bd ready              # Show unblocked tasks
bd ready --json       # JSON output for agents
bd list               # Show all issues
bd show <issue-id>    # Show details
```

### Creating Issues
```bash
bd create "Task title" -t task -p 1 --json
bd create "Bug description" -t bug -p 0 --json

# Best practice: Preserve original user specification
bd create --title="Task name" --type=task --priority=2 --description="<structured description>"
bd comments add <issue-id> "# Raw Specification (Original)\n\n<paste user's exact original text here>"
```

### Working on Issues
```bash
bd update <issue-id> --status in_progress
bd close <issue-id> --reason "Description of what was done"
bd sync              # Sync to git (run at session end)
```

### Managing Dependencies
```bash
bd dep add <issue-B> <issue-A>  # issue-A blocks issue-B
bd dep tree <issue-id>           # Show dependency tree
```

### Using bv as AI Sidecar
Instead of parsing JSONL manually, use bv's robot commands for deterministic graph intelligence:

```bash
bv --robot-help        # Show all AI-facing commands
bv --robot-insights    # Graph metrics (PageRank, Betweenness, etc.)
bv --robot-plan        # Execution plan with parallel tracks
bv --robot-priority    # Priority recommendations
```

## Documentation
- **Cheatsheet**: `./docs-internal/bd-bv/bd_bv_cheatsheet.md`
- **Overview**: `./docs-internal/bd-bv/bd-bv-overview.md`
- **bd Reference**: `./docs-internal/bd-bv/bd.md`
- **bv Reference**: `./docs-internal/bd-bv/bv.md`

# Commit Message Guidelines

- Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) format
- Include a brief description of the change, its motivation, and any relevant issue references
- Never mention CLAUDE in commit messages or code comments
