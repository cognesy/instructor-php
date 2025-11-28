# Multi-Agent bd/bv Collaboration Instructions

Instructions for setting up realistic multi-agent collaboration using bd/bv across separate Claude Code instances.

---

## 🎯 The Realistic Workflow Setup

### **Window 1 (Planning Agent)**
- Creates issues
- Does research/discovery
- Breaks down work
- Tracks dependencies
- Reviews progress

### **Window 2 (Fresh Claude Instance - Execution Agent)**
- Sees what's ready (`bd ready`)
- Picks a task
- Implements the code
- Closes the issue when done
- Syncs back

---

## 📋 Instructions for Execution Agent (Window 2)

Copy this message and paste it into your **other Claude Code window**:

```markdown
I have a beads-tracked project with issues ready to work on. Please help me implement them.

First, check what work is available:

```bash
bd ready
bd show partnerspot-42n
```

Then work on **partnerspot-42n**: Extract hardcoded error messages in DictCommands to translations.

The issue description says:
"Found hardcoded strings in Console Commands: 'No updates specified', 'Specify a dictionary code', 'Failed to add item', etc. These should use __() helper"

Please:
1. Start the issue: `bd update partnerspot-42n --status in_progress`
2. Find and review the hardcoded strings in `app/Features/Dictionaries/Console/Commands/`
3. Check existing translation infrastructure in `./docs/i18n/` and `lang/` directories
4. Extract the strings to appropriate translation files
5. Replace hardcoded strings with `__()` helper calls
6. Test the changes work
7. When complete: `bd close partnerspot-42n --reason "Extracted N strings to translations"`
8. Sync: `bd sync`

**Important**: You don't have the full context from my planning session - that's intentional! Use `bd show <id>` and `bd dep tree <id>` to understand the work.
```

---

## 🔍 What Will Happen (The Magic)

### **1. Fresh Context**
The other agent will:
- Run `bd ready` → See 2 available tasks
- Run `bd show partnerspot-42n` → Read the issue description
- Start from the issue description **only** (no memory of planning session)

### **2. Work Discovery**
The other agent will:
- Explore the codebase independently
- Make its own decisions about implementation
- Possibly discover **new issues** while working
- Create those issues with `bd create "..."`

### **3. Automatic Sync**
When the other agent runs `bd sync`:
- Changes appear in `.beads/issues.jsonl`
- Git hooks automatically handle the commit
- You can see the updates in real-time

### **4. Monitor Progress**
In the planning window, you can watch:
```bash
# See status updates
bd show partnerspot-42n

# Watch for changes
watch -n 5 'bd list'

# Or use bv visual UI
bv
```

---

## 🎓 What This Demonstrates

### **Key Insight #1: Agents Share State via Git**
- Other agent marks task `in_progress`
- Runs `bd sync`
- Git push happens
- You run `git pull` + `bd sync`
- You see the status update!

### **Key Insight #2: Issues Are Documentation**
- The other agent has **zero context** from planning conversation
- But it has **perfect context** from the issue:
  - Title: "Extract hardcoded error messages..."
  - Description: Lists the specific strings
  - Dependencies: Shows it came from reviewing backend
  - Related: Links to parent task

### **Key Insight #3: Discovery During Work**
The other agent might:
- Find more strings than documented
- Discover translation keys are missing
- Create new issues: `bd create "Add missing translation keys" -t bug -p 1`
- Link them: `bd dep add <new-issue> partnerspot-42n --type discovered-from`

---

## 👀 How to Monitor from Planning Window

While the other agent works, you can:

### **Option 1: Watch Status in Real-Time**

```bash
# Use the monitoring script
./monitor-bd-progress.sh
```

This refreshes every 30 seconds and shows:
- Issue status
- Last update time
- Other ready tasks

### **Option 2: Use bv TUI**

```bash
bv
# Press 'r' to filter ready tasks
# You'll see status change from "open" → "in_progress" → "closed"
```

### **Option 3: Check Manually**

```bash
# Pull latest
git pull

# Import changes
bd sync --import-only

# Check status
bd show partnerspot-42n
bd list
```

---

## 🔄 Expected Flow Timeline

Here's what you should see:

### **T+0 minutes** (Other agent starts)
```bash
# In planning window:
bd show partnerspot-42n
# Status: open

# Other agent runs:
bd update partnerspot-42n --status in_progress
bd sync
```

### **T+2 minutes** (You pull updates)
```bash
# In planning window:
git pull
bd sync --import-only
bd show partnerspot-42n
# Status: in_progress ✓
```

### **T+5-10 minutes** (Other agent working)
```bash
# Other agent might create new issues:
bd create "Add 'errors' translation keys to lang files" -t task -p 1
bd dep add <new-id> partnerspot-42n --type discovered-from
bd sync
```

### **T+10-15 minutes** (Other agent completes)
```bash
# Other agent:
bd close partnerspot-42n --reason "Extracted 12 hardcoded strings to lang/*/ui.php"
bd sync

# In planning window:
git pull
bd sync --import-only
bd show partnerspot-42n
# Status: closed ✓
# Close reason visible
```

---

## 🎬 Complete Setup Guide

### **Step 1: Start Monitoring (Planning Window)**

Run this to watch progress in real-time:

```bash
./monitor-bd-progress.sh
```

This will refresh every 30 seconds and show you when the other agent updates the issue.

### **Step 2: Give Instructions (Execution Window)**

Copy and paste this into your other Claude Code instance:

```
I have a beads-tracked project with issues ready to work on. Please help me implement them.

First, check what work is available:

bd ready
bd show partnerspot-42n

Then work on partnerspot-42n: Extract hardcoded error messages in DictCommands to translations.

Please:
1. Start: bd update partnerspot-42n --status in_progress
2. Find hardcoded strings in app/Features/Dictionaries/Console/Commands/
3. Check docs/i18n/ and lang/ for translation infrastructure
4. Extract strings to appropriate translation files
5. Replace with __() helper calls
6. Test the changes
7. Close: bd close partnerspot-42n --reason "Extracted N strings to translations"
8. Sync: bd sync

Use bd show and bd dep tree to understand context if needed.
```

### **Step 3: Observe the Magic**

You'll see in the planning window:
- Status changes from "open" → "in_progress"
- Possibly new issues created (if agent discovers more work)
- Status changes to "closed" when done
- Close reason explaining what was accomplished
- Code commits in git log

---

## 🎯 What Makes This Realistic

1. **Fresh Agent = No Context Pollution**
   - The other agent doesn't know about planning conversation
   - It only has what's in the issue description
   - Just like a real team member picking up a ticket

2. **Async Collaboration**
   - You're not watching them code
   - You're monitoring via bd/bv tools
   - Git acts as the communication layer

3. **Discovery During Work**
   - Other agent might find issues we missed
   - Creates new beads to track them
   - Shows how work naturally expands

4. **Real Git Workflow**
   - Separate commits for beads vs. code
   - Hooks handle synchronization
   - You can see both in git log

---

## 📝 Generate Completion Report

After the other agent finishes, run:

```bash
# Generate completion report
bv --export-md dictionaries-translation-work.md

# See what changed
git log --oneline --since="30 minutes ago"

# See final stats
bd stats

# Review all changes
bd list
```

---

## 🔄 General Template for Any Task

Use this template to delegate any bd task to a fresh agent:

```markdown
I have a beads-tracked project with issues ready to work on.

First, check what work is available:

```bash
bd ready
bd show <issue-id>
```

Then work on **<issue-id>**: <brief description>

Please:
1. Start: `bd update <issue-id> --status in_progress`
2. <Implementation step 1>
3. <Implementation step 2>
4. <Implementation step 3>
5. Test the changes
6. Close: `bd close <issue-id> --reason "What you accomplished"`
7. Sync: `bd sync`

Use `bd show <id>` and `bd dep tree <id>` to understand context if needed.
```

---

## 💡 Advanced Scenarios

### **Scenario 1: Parallel Work**

Start multiple agents on different tracks:

```bash
# Check parallel tracks
bv --robot-plan | jq '.plan.tracks'

# Assign different tracks to different agents:
# Agent 1: partnerspot-42n
# Agent 2: partnerspot-8aa
# Agent 3: partnerspot-iql
```

### **Scenario 2: Dependent Work**

Start second agent when first completes:

```bash
# Agent 1 completes partnerspot-8aa
# Monitor: bd show partnerspot-3uv
# When unblocked, start Agent 2 on partnerspot-3uv
```

### **Scenario 3: Review & Feedback**

Planning agent reviews execution agent's work:

```bash
# After execution agent closes issue:
git pull
bd show <issue-id>
git log -p --since="1 hour ago" -- '*.php' '*.tsx'

# If issues found:
bd create "Fix issue found in review" -t bug -p 1
bd dep add <new-issue> <completed-issue> --type discovered-from
```

---

## 🐛 Troubleshooting

### **Issue: Execution agent can't see updates**

```bash
# In execution window:
git pull
bd sync --import-only
bd list
```

### **Issue: Monitoring script not showing changes**

```bash
# Check git sync
git pull -v

# Check bd sync
bd sync --status

# Force import
bd sync --import-only
```

### **Issue: Hooks not syncing automatically**

```bash
# Verify hooks are installed
ls -la .git/hooks/ | grep -v sample

# Check hook versions
head -2 .git/hooks/pre-commit
```

---

## 📚 Additional Resources

- **bd/bv Overview**: `./docs/_ext/bd-bv-overview.md`
- **bd/bv Cheatsheet**: `./docs/_ext/bd_bv_cheatsheet.md`
- **Full bd Docs**: `./docs/_ext/bd.md`
- **Full bv Docs**: `./docs/_ext/bv.md`
- **Project Integration**: `CLAUDE.md`

---

## ✅ Pre-Flight Checklist

Before starting multi-agent collaboration:

**Planning Agent:**
- [ ] `bd list` - Verify issues exist
- [ ] `bd ready` - Confirm tasks are unblocked
- [ ] `git status` - Ensure clean state
- [ ] `bd sync` - Push latest issue state
- [ ] `./monitor-bd-progress.sh` - Start monitoring

**Execution Agent:**
- [ ] `bd info` - Verify database access
- [ ] `bd ready` - See available work
- [ ] `git pull` - Get latest state
- [ ] `bd sync --import-only` - Import issues

---

## 🎯 Success Criteria

You'll know the workflow is working when:

1. ✅ Execution agent can see issues (`bd ready` shows tasks)
2. ✅ Status updates propagate (planning agent sees "in_progress")
3. ✅ New issues appear (execution agent creates discovered work)
4. ✅ Completion tracked (issue closes with reason)
5. ✅ Code changes committed (separate from bd sync commits)
6. ✅ Both agents can query final state (`bd stats`, `bd list`)
