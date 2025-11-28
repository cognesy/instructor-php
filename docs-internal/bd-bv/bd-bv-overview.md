# bd & bv Overview: AI-Powered Issue Tracking

A comprehensive guide to how **bd** (beads) and **bv** (beads viewer) transform your development workflow.

---

## 🎯 The Core Concept

**bd** (beads) and **bv** (beads viewer) form a two-part system:

- **bd**: A git-backed issue tracker that agents use to manage work
- **bv**: An intelligent terminal UI and graph analysis engine that makes sense of the work

Think of bd as the **memory** and bv as the **brain**.

---

## 🔗 How bd (Beads) Helps You

### 1. Agent Memory Upgrade
Your AI coding agents suffer from "amnesia" when sessions end. With bd:
- ✅ Agents can file issues **during work** when they discover bugs or TODOs
- ✅ Agents never "forget" incomplete work across sessions
- ✅ Agents can track dependencies properly (Issue A blocks Issue B)

### 2. Git-Backed Database Magic
Here's the clever part - bd acts like a centralized database but is actually **distributed via git**:

```bash
# On your laptop
bd create "Fix auth bug" -p 1
bd update bd-a1b2 --status in_progress
# Changes auto-export to .beads/beads.jsonl after 5 seconds

git add .beads/
git commit -m "Working on auth"
git push

# On your desktop (or coworker's machine)
git pull
bd list  # Automatically sees the new issue!
```

No PostgreSQL. No server. Just git doing what it does best.

### 3. Four Types of Dependencies
- **blocks**: Hard blocker (can't start B until A is done)
- **related**: Soft connection
- **parent-child**: Hierarchical breakdown
- **discovered-from**: Links discovered work back to parent task

### 4. Hash-Based IDs (v0.20.1+)
No more ID collisions when multiple agents/branches work simultaneously:
- Old: `bd-1`, `bd-2` (sequential → collisions)
- New: `bd-a1b2`, `bd-f14c` (hash → collision-free)

---

## 🧠 How bv (Beads Viewer) Helps You

### 1. The AI Sidecar
bv is your **graph intelligence engine**. Instead of agents trying to compute dependencies manually (and potentially hallucinating), they can call:

```bash
# Get pre-computed graph insights
bv --robot-insights

# Get execution plan (what to work on next)
bv --robot-plan

# Get priority recommendations
bv --robot-priority
```

This gives agents:
- ✅ **Bottlenecks** (Betweenness centrality) - tasks blocking many others
- ✅ **Keystones** (Critical path) - tasks with zero slack
- ✅ **Cycles** (Circular dependencies) - structural errors to fix
- ✅ **Execution order** (Topological sort) - valid work sequence

### 2. Nine Graph Metrics
bv computes sophisticated metrics on your dependency graph:

| Metric | What It Finds | Why It Matters |
|--------|---------------|----------------|
| **PageRank** | Foundational blockers | High PageRank = Many tasks depend on this |
| **Betweenness** | Bottlenecks | High Betweenness = Bridges clusters, blocks parallelism |
| **HITS** | Epics (Hubs) vs. Utilities (Authorities) | Helps prioritize foundational work |
| **Critical Path** | Longest dependency chain | Tasks with zero slack - delays cascade |
| **Cycles** | Circular dependencies | Logical impossibilities to fix |
| **Eigenvector** | Influential neighbors | Connected to important tasks |
| **Degree** | Direct connections | Immediate blockers/blocked |
| **Density** | Graph interconnectedness | Project coupling health |
| **Topo Sort** | Valid execution order | Work queue foundation |

### 3. Beautiful Terminal UI
For humans:
- **Split-view dashboard**: List + details side-by-side
- **Kanban board** (`b`): Visual workflow columns
- **Graph visualizer** (`g`): ASCII art dependency graph
- **Insights dashboard** (`i`): See bottlenecks, keystones, cycles
- **Time-travel** (`t`): Compare against any git revision

### 4. Zero-Latency Filtering
- Press `o` for Open, `c` for Closed, `r` for Ready (unblocked)
- Press `/` for fuzzy search across titles, IDs, assignees, labels
- All client-side - no database queries, no spinners

---

## 🔄 How They Integrate Into Your Workflow

### During Development (Agent Workflow)

```bash
# 1. Agent starts session - finds ready work
bd ready --json | jq '.[0]'
# or
bv --robot-plan  # Get parallel execution tracks

# 2. Agent works on task, discovers a bug
bd create "Memory leak in parser" -t bug -p 0 --json
bd dep add <new-bug-id> <current-task-id> --type discovered-from

# 3. Agent updates status
bd update bd-a1b2 --status in_progress

# 4. Agent completes work
bd close bd-a1b2 --reason "Implemented OAuth flow"

# 5. Session end - sync to git
bd sync
git add .beads/
git commit -m "Completed bd-a1b2"
git push
```

### For You (Human Oversight)

```bash
# Quick terminal UI
bv                          # Launch interactive TUI

# Export status report for stakeholders
bv --export-md report.md    # Generates Markdown with Mermaid diagrams

# Compare progress over time
bv --diff-since HEAD~10     # What changed in last 10 commits?

# Find what to work on next
bv --recipe actionable      # Show unblocked tasks
bv --recipe high-impact     # Show top PageRank tasks
```

### Integration Points in Your Project

bd is integrated via the `SessionStart` hook. Here's how it works:

1. **Automatic Setup**: When agents start a session, the hook runs `bd prime` to load context
2. **CLAUDE.md Instructions**: Agents see the bd workflow instructions automatically
3. **Session Close Protocol**: At session end, agents should:
   - Create/update/close issues
   - Run `bd sync` to commit changes
   - Push to git

---

## 📊 Real-World Example

Let's say you ask an agent to "refactor the authentication system":

**Without bd/bv:**
```
❌ Agent writes a TODO list in markdown
❌ Agent might forget some subtasks
❌ Dependencies unclear
❌ Next session: "What was I working on?"
```

**With bd/bv:**
```bash
# Agent creates structured issues
bd create "Refactor auth system" -t epic -p 1
# Returns: bd-a3f8

bd create "Extract OAuth logic" -p 1 --parent bd-a3f8
bd create "Update login tests" -p 1 --parent bd-a3f8
bd create "Migrate session storage" -p 1 --parent bd-a3f8

# Add dependencies
bd dep add bd-a3f8.2 bd-a3f8.1  # Tests depend on OAuth extraction

# You can check progress
bv --robot-plan
# Shows: bd-a3f8.1 is actionable, completes bd-a3f8.2 & bd-a3f8.3

# Next session
bd ready
# Shows exactly what's unblocked and ready to work
```

---

## 🎁 Key Benefits

### For AI Agents
1. **No more forgotten work** - Everything tracked in git
2. **Proper dependency handling** - Can't work on blocked tasks
3. **Context recovery** - `bd ready` shows exactly what's available
4. **Audit trail** - All changes logged

### For Humans
1. **Visibility** - `bv` shows beautiful dashboards of work state
2. **Intelligence** - Graph metrics reveal hidden bottlenecks
3. **Time-travel** - Compare progress across git history
4. **Collaboration** - Issues sync via git, no external service needed

### For Teams
1. **Distributed** - Each clone has full database via git
2. **Offline-first** - SQLite cache for fast local queries
3. **No collisions** - Hash-based IDs prevent merge conflicts
4. **Multi-repo support** - Workspace config unifies monorepos

---

## 💡 How to Get Started

### For This Project

bd is already integrated! To maximize value:

1. **Let agents use bd naturally** - They'll file issues as they discover work
2. **Install bv** for yourself:
   ```bash
   curl -fsSL https://raw.githubusercontent.com/Dicklesworthstone/beads_viewer/main/install.sh | bash
   ```

3. **Run bv periodically** to see project health:
   ```bash
   bv              # Interactive UI
   bv -i           # Insights dashboard
   bv --robot-plan # What should be worked on next?
   ```

4. **Review at session end** - Check `bv` before closing sessions to ensure nothing is lost

---

## 🚀 The Real Power

The magic happens when bd and bv work together:

- **bd** gives agents **structured memory** across sessions
- **bv** gives both agents and humans **graph intelligence** to prioritize correctly

Instead of agents creating markdown TODOs that rot, they use bd to build a **living dependency graph** that bv can analyze with sophisticated algorithms (PageRank, Betweenness, Critical Path) to surface what truly matters.

**Result**: Agents work smarter, you have better visibility, and nothing falls through the cracks.

---

## 📖 Further Reading

- **bd Documentation**: `./docs/_ext/bd.md` - Complete bd reference
- **bv Documentation**: `./docs/_ext/bv.md` - Complete bv reference
- **CLAUDE.md**: Project-specific bd integration instructions

---

## 🔑 Key Commands Quick Reference

### bd (Beads CLI)

```bash
# Find work
bd ready                     # Show unblocked tasks
bd ready --json             # JSON output for agents
bd list --status=open       # List open issues

# Create issues
bd create "Task title" -t bug -p 1
bd create --from-template epic "Epic title"

# Update issues
bd update bd-a1b2 --status in_progress
bd close bd-a1b2 --reason "Completed"

# Dependencies
bd dep add bd-f14c bd-a1b2  # bd-f14c depends on bd-a1b2
bd dep tree bd-f14c         # Show dependency tree

# Sync
bd sync                     # Sync with git
```

### bv (Beads Viewer)

```bash
# Interactive UI
bv                          # Launch TUI

# Robot protocol (for agents)
bv --robot-insights        # Graph metrics JSON
bv --robot-plan           # Execution plan JSON
bv --robot-priority       # Priority recommendations JSON

# Time-travel
bv --diff-since HEAD~5    # Compare with 5 commits ago
bv --as-of v1.0.0         # View state at tag

# Recipes
bv --recipe actionable    # Ready to work
bv --recipe high-impact   # Top PageRank
bv --recipe stale         # Untouched 30+ days

# Export
bv --export-md report.md  # Generate Markdown report
```

### bv TUI Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `j`/`k` | Navigate list |
| `o` | Filter: Open |
| `c` | Filter: Closed |
| `r` | Filter: Ready (unblocked) |
| `/` | Search |
| `b` | Kanban board |
| `i` | Insights dashboard |
| `g` | Graph view |
| `t` | Time-travel mode |
| `E` | Export to Markdown |
| `q` | Quit |

---

## 🎯 Best Practices

### For AI Agents

1. **Use `--json` flags** for all bd commands - Makes output easy to parse
2. **Create issues proactively** - File issues as you discover work, don't wait
3. **Link discovered work** - Use `bd dep add --type discovered-from`
4. **Close with context** - Always provide `--reason` when closing
5. **Sync at session end** - Run `bd sync` before ending session

### For Humans

1. **Check bv insights regularly** - Spot bottlenecks and cycles early
2. **Use recipes** - Pre-configured views save time
3. **Export reports** - Share status with stakeholders
4. **Time-travel for retrospectives** - Compare sprint progress
5. **Trust the graph metrics** - PageRank reveals true priorities

### For Teams

1. **Commit .beads/ to git** - Source of truth for all clones
2. **Use protected branches workflow** - `bd init --branch beads-metadata`
3. **Install git hooks** - Auto-sync on commits/merges
4. **Configure merge driver** - Prevents JSONL conflicts
5. **Use workspace config** - Unify multi-repo projects
