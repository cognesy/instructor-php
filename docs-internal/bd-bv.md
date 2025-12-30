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
