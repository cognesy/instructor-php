# bd & bv Cheatsheet

Quick reference for the bd/bv workflow based on real usage patterns.

---

## 🎯 The 5-Minute Quick Start

### **What Are These Tools?**

- **bd** = Git-backed issue tracker (your agent's memory)
- **bv** = Terminal UI + graph analysis engine (your agent's brain)

### **Core Workflow**

```bash
# 1. Check what's ready to work on
bd ready

# 2. Start working
bd update <issue-id> --status in_progress

# 3. Discover new work while coding
bd create "Fix bug X" -t bug -p 1

# 4. Complete work
bd close <issue-id> --reason "Implemented feature"

# 5. Sync everything to git
bd sync
```

---

## 📋 bd Commands Reference

### **Finding Work**

```bash
# Show unblocked tasks (no dependencies)
bd ready

# Show all issues
bd list

# Filter by status
bd list --status=open
bd list --status=in_progress
bd list --status=closed

# Get statistics
bd stats

# Show specific issue details
bd show <issue-id>
```

### **Creating Issues**

```bash
# Basic creation
bd create "Task title" -t task -p 1

# With description
bd create "Task title" -t bug -p 0 -d "Detailed description"

# Issue types: bug, feature, task, epic, chore
# Priority: 0 (highest) to 4 (lowest)

# Get JSON output (for agents)
bd create "Task" -t task -p 1 --json
```

### **Updating Issues**

```bash
# Change status
bd update <issue-id> --status open
bd update <issue-id> --status in_progress
bd update <issue-id> --status blocked

# Change priority
bd update <issue-id> --priority 0

# Close with reason
bd close <issue-id> --reason "Completed successfully"
```

### **Managing Dependencies**

```bash
# Add blocker (issue-B blocks issue-A)
bd dep add <issue-A> <issue-B> --type blocks

# Link discovered work
bd dep add <new-issue> <parent-issue> --type discovered-from

# Show dependency tree
bd dep tree <issue-id>

# Dependency types:
# - blocks: Hard blocker (affects bd ready)
# - related: Soft relationship
# - parent-child: Hierarchical
# - discovered-from: Found during work on parent
```

### **Syncing with Git**

```bash
# Full sync (commit + push)
bd sync

# Check sync status
bd sync --status

# Manual operations (rarely needed)
bd sync --flush-only     # Just flush to JSONL
bd sync --import-only    # Just import from JSONL
```

---

## 🧠 bv Commands Reference

### **Robot Protocol (for AI Agents)**

```bash
# Get graph metrics (PageRank, Betweenness, etc.)
bv --robot-insights

# Get execution plan with parallel tracks
bv --robot-plan

# Get priority recommendations
bv --robot-priority

# Show available recipes
bv --robot-recipes

# Get help for AI agents
bv --robot-help
```

### **Interactive TUI**

```bash
# Launch interactive UI
bv

# Keyboard shortcuts (in TUI):
# j/k         - Navigate up/down
# o           - Filter: Open issues
# c           - Filter: Closed issues
# r           - Filter: Ready (unblocked)
# /           - Search
# b           - Kanban board view
# i           - Insights dashboard
# g           - Graph visualizer
# t           - Time-travel mode
# E           - Export to Markdown
# q           - Quit
```

### **Time-Travel & Comparison**

```bash
# View state at specific point
bv --as-of HEAD~10
bv --as-of v1.0.0
bv --as-of 2024-01-15

# Show changes since point in time
bv --diff-since HEAD~5
bv --diff-since v1.0.0
bv --diff-since 2024-01-01

# Get diff as JSON
bv --diff-since HEAD~5 --robot-diff
```

### **Recipes (Pre-configured Views)**

```bash
# Apply built-in recipes
bv --recipe actionable      # Ready to work (no blockers)
bv --recipe high-impact     # Top PageRank scores
bv --recipe stale           # Untouched 30+ days
bv --recipe blocked         # Waiting on dependencies
bv --recipe recent          # Updated in last 7 days

# Short form
bv -r actionable
```

### **Export & Reports**

```bash
# Generate Markdown report with Mermaid diagrams
bv --export-md report.md

# With recipe filter
bv --recipe actionable --export-md sprint-work.md
```

---

## 🔄 Complete Work Cycle Example

### **Scenario: Add i18n Translations**

#### **1. Session Start**

```bash
# Check what's available
bd ready

# Create main task
bd create "Find missing translations in Dictionaries module" \
  -t feature -p 1 \
  -d "Review backend and frontend for hardcoded strings"

# Returns: partnerspot-abc
```

#### **2. Break Down Work**

```bash
# Start main task
bd update partnerspot-abc --status in_progress

# Create subtasks
bd create "Review backend code" -t task -p 1
# Returns: partnerspot-def

bd create "Review frontend code" -t task -p 1
# Returns: partnerspot-ghi

bd create "Generate translations" -t task -p 2
# Returns: partnerspot-jkl

# Add dependencies (translation depends on reviews)
bd dep add partnerspot-jkl partnerspot-def --type blocks
bd dep add partnerspot-jkl partnerspot-ghi --type blocks
```

#### **3. Work & Discover Issues**

```bash
# Work on first subtask
bd update partnerspot-def --status in_progress

# While working, discover hardcoded strings
bd create "Extract hardcoded error messages" -t bug -p 1
# Returns: partnerspot-mno

# Link to parent
bd dep add partnerspot-mno partnerspot-def --type discovered-from

# Complete subtask
bd close partnerspot-def \
  --reason "Reviewed, found hardcoded strings tracked in partnerspot-mno"
```

#### **4. Check Status**

```bash
# What's ready now?
bd ready
# Shows: partnerspot-ghi and partnerspot-mno

# Full status
bd list
bd stats

# Dependency tree
bd dep tree partnerspot-jkl
```

#### **5. Use AI Intelligence**

```bash
# Get insights
bv --robot-insights | jq '.Keystones'
# Shows critical path items

# Get execution plan
bv --robot-plan | jq '.plan.summary'
# Shows: highest_impact, unblocks_count

# Get priority recommendations
bv --robot-priority | jq '.recommendations[0]'
```

#### **6. Session Close**

```bash
# Sync to git
bd sync
# - Flushes changes to JSONL
# - Commits to git
# - Pulls from remote
# - Imports changes
# - Pushes

# Verify
git status
```

---

## 🎓 Understanding bv Graph Metrics

### **Key Metrics Explained**

| Metric | What It Means | Use Case |
|--------|---------------|----------|
| **PageRank** | Recursive importance | Find foundational blockers |
| **Betweenness** | Bottleneck position | Find tasks blocking many others |
| **Critical Path** | Longest chain | Find keystones with zero slack |
| **HITS Hub** | Dependency aggregator | Find epics |
| **HITS Authority** | Widely depended on | Find utilities |
| **Eigenvector** | Connected to important tasks | Find strategic dependencies |

### **Interpreting Insights**

```bash
bv --robot-insights | jq '.'
```

**Output interpretation:**

```json
{
  "Keystones": [
    {"ID": "task-A", "Value": 5}  // On critical path, depth=5
  ],
  "Bottlenecks": [
    {"ID": "task-B", "Value": 0.8}  // High betweenness, blocks many paths
  ],
  "Authorities": [
    {"ID": "task-C", "Value": 0.9}  // Many tasks depend on this
  ]
}
```

- **Keystones**: Complete these first - delays cascade
- **Bottlenecks**: Parallelism blockers - prioritize to unblock work
- **Authorities**: Foundation tasks - stabilize early

---

## 🚨 Session Close Protocol

**CRITICAL**: Before ending any work session, run this checklist:

```bash
# 1. Review open work
bd list --status=in_progress

# 2. Close completed issues
bd close <issue-id> --reason "..."

# 3. Create issues for discovered work
bd create "..." -t bug -p 1

# 4. Sync everything
bd sync

# 5. Verify clean state
git status

# 6. Final check
bd stats
```

**Never skip `bd sync`** - work is not done until pushed.

---

## 🔧 Git Hooks (Automatic)

Your repo has these hooks installed:

### **pre-commit**
- Flushes bd changes to JSONL before commit
- Auto-stages `.beads/issues.jsonl`

### **post-merge**
- Imports bd changes after `git pull`
- Keeps local database in sync

### **pre-push**
- Validates no uncommitted changes
- Offers interactive sync if needed

### **post-checkout**
- Imports bd changes after branch switch

### **Verify Hooks**

```bash
# Check hooks are installed
ls -la .git/hooks/ | grep -E '(pre-commit|post-merge|pre-push|post-checkout)' | grep -v sample

# Check versions
head -2 .git/hooks/pre-commit
# Should show: bd-hooks-version: 0.26.0
```

---

## 💡 Best Practices

### **For AI Agents**

1. ✅ **Always use `--json` flag** for programmatic parsing
2. ✅ **Create issues proactively** - file as you discover
3. ✅ **Link discovered work** - use `discovered-from` type
4. ✅ **Close with context** - always provide `--reason`
5. ✅ **Sync at session end** - run `bd sync` before finishing

### **For Humans**

1. ✅ **Check `bd ready`** regularly - see what's unblocked
2. ✅ **Use `bv` for visualization** - see dependencies graphically
3. ✅ **Export reports** - `bv --export-md` for stakeholders
4. ✅ **Time-travel** - `bv --diff-since` for retrospectives
5. ✅ **Trust graph metrics** - PageRank reveals true priorities

### **For Teams**

1. ✅ **Commit `.beads/` to git** - source of truth
2. ✅ **Use protected branches workflow** - `bd init --branch beads-metadata`
3. ✅ **Install git hooks** - auto-sync on commits/merges
4. ✅ **Configure merge driver** - prevents JSONL conflicts
5. ✅ **Use workspace config** - unify multi-repo projects

---

## 🐛 Troubleshooting

### **Issue: Changes not syncing**

```bash
# Check daemon status
bd info

# Force sync
bd sync --flush-only

# Check git status
git status
```

### **Issue: Dependency cycle detected**

```bash
# Find cycles
bd dep cycles

# Show full tree
bd dep tree <issue-id>

# Remove incorrect dependency
bd dep remove <issue-A> <issue-B>
```

### **Issue: Hook not firing**

```bash
# Check hooks are executable
ls -la .git/hooks/pre-commit

# Reinstall hooks
cd .beads
# (hooks are installed during bd init)
```

### **Issue: Database out of sync**

```bash
# Check health
bd doctor --check-health

# Force import
bd sync --import-only

# Full sync
bd sync
```

---

## 📊 Example Output Reference

### **bd ready**

```
📋 Ready work (2 issues with no blockers):

1. [P1] partnerspot-nza: Review backend Dictionaries code
2. [P1] partnerspot-8aa: Review frontend Dictionaries UI
```

### **bd list**

```
partnerspot-42n [P1] [bug] open - Extract hardcoded error messages
partnerspot-8aa [P1] [task] open - Review frontend UI
partnerspot-nza [P1] [task] closed - Review backend code
partnerspot-iql [P1] [feature] in_progress - Find translations
partnerspot-3uv [P2] [task] open - Generate translations
```

### **bd stats**

```
📊 Beads Statistics:

Total Issues:      5
Open:              3
In Progress:       1
Closed:            1
Blocked:           1
Ready:             2
```

### **bd dep tree**

```
🌲 Dependency tree for partnerspot-3uv:

partnerspot-3uv: Generate translations [P2] (open) [BLOCKED]
    ├── partnerspot-8aa: Review frontend UI [P1] (open)
    └── partnerspot-nza: Review backend code [P1] (closed)
```

### **bv --robot-plan**

```json
{
  "plan": {
    "tracks": [
      {
        "track_id": "track-A",
        "items": [{"id": "task-1", "unblocks": ["task-3"]}]
      },
      {
        "track_id": "track-B",
        "items": [{"id": "task-2", "unblocks": null}]
      }
    ],
    "summary": {
      "highest_impact": "task-1",
      "impact_reason": "Unblocks 1 task",
      "unblocks_count": 1
    }
  }
}
```

---

## 🔗 Quick Links

- **Full bd docs**: `./docs/_ext/bd.md`
- **Full bv docs**: `./docs/_ext/bv.md`
- **Overview**: `./docs/_ext/bd-bv-overview.md`
- **CLAUDE.md**: Project integration instructions

---

## 📝 Common Patterns

### **Pattern: Epic with Subtasks**

```bash
# Create epic
bd create "Implement OAuth" -t epic -p 1
# Returns: epic-001

# Create subtasks
bd create "Design OAuth flow" -t task -p 1
bd create "Implement backend" -t task -p 1
bd create "Implement frontend" -t task -p 1

# Link as children (optional)
bd dep add subtask-001 epic-001 --type parent-child
```

### **Pattern: Parallel Work Streams**

```bash
# Create independent tasks
bd create "Backend API" -t task -p 1
bd create "Frontend UI" -t task -p 1
bd create "Documentation" -t task -p 2

# Check parallel tracks
bv --robot-plan | jq '.plan.tracks'
# Shows 3 independent tracks
```

### **Pattern: Discovered Bug**

```bash
# While working on task-A
bd update task-A --status in_progress

# Discover bug
bd create "Memory leak in parser" -t bug -p 0
# Returns: bug-001

# Link to parent
bd dep add bug-001 task-A --type discovered-from
```

### **Pattern: Sprint Planning**

```bash
# See high-impact work
bv --recipe high-impact

# Export sprint report
bv --recipe actionable --export-md sprint-plan.md

# Get execution plan
bv --robot-plan | jq '.plan.summary'
```

---

## ⚡ Power User Tips

### **Tip 1: Combine bd and jq**

```bash
# Get next ready task
bd ready --json | jq '.[0] | .id'

# Count issues by status
bd list --json | jq 'group_by(.status) | map({status: .[0].status, count: length})'

# Find high-priority open issues
bd list --json | jq '[.[] | select(.status == "open" and .priority <= 1)]'
```

### **Tip 2: Use bv for triage**

```bash
# See bottlenecks
bv --robot-insights | jq '.Bottlenecks'

# Find misaligned priorities
bv --robot-priority | jq '.recommendations[] | select(.confidence > 0.8)'

# See what's blocked
bd list --status=open | grep -i blocked
```

### **Tip 3: Automate with scripts**

```bash
#!/bin/bash
# auto-triage.sh - Daily triage report

echo "=== Daily Triage Report ==="
echo "Ready work:"
bd ready

echo -e "\nHigh-impact tasks:"
bv --robot-plan | jq -r '.plan.summary.highest_impact'

echo -e "\nBottlenecks:"
bv --robot-insights | jq -r '.Bottlenecks[0].ID'

echo -e "\nBlocked tasks:"
bd list --json | jq -r '.[] | select(.status == "blocked") | .id'
```

---

## 🎯 Final Checklist

Before starting your first work session:

- [ ] `bd info` - Verify database is initialized
- [ ] `bd ready` - Check for existing work
- [ ] `bv --robot-help` - Read AI agent guide
- [ ] `git log -1` - Confirm hooks are working (should see bd sync commits)
- [ ] `bv` - Try the interactive TUI (press `q` to exit)

Before ending every session:

- [ ] `bd list --status=in_progress` - Review active work
- [ ] `bd close <id> --reason "..."` - Close completed work
- [ ] `bd create "..."` - File discovered issues
- [ ] `bd sync` - Sync to git
- [ ] `git status` - Verify clean state
- [ ] `bd stats` - Final status check
