# bd/bv Setup Guide for Developers

This guide helps you set up the bd/bv issue tracking system for instructor-php.

## Prerequisites

- Node.js installed (for npm)
- Git configured
- Access to instructor-php repository

## Installation Steps

### 1. Install bd and bv CLI Tools

**bd (beads issue tracker):**
```bash
curl -fsSL https://raw.githubusercontent.com/steveyegge/beads/main/scripts/install.sh | bash
```

**bv (beads viewer):**
```bash
curl -fsSL https://raw.githubusercontent.com/Dicklesworthstone/beads_viewer/main/install.sh | bash
```

Verify installation:
```bash
bd --help
bv --help
```

### 2. Initialize bd in the Repository

```bash
cd /path/to/instructor-php
bd init --quiet  # Auto-installs git hooks, no prompts
```

This creates:
- `.beads/` directory (gitignored, but synced via JSONL)
- Git hooks in `.git/hooks/` (pre-commit, post-merge)
- Imports existing issues from `.beads/issues.jsonl` if present

### 3. Configure Claude Code Hooks (Recommended)

**Option A: Run the setup script (easiest)**

```bash
./docs-internal/bd-bv/setup-claude-hooks.sh
```

**Option B: Manual setup**

1. Create hooks directory:
   ```bash
   mkdir -p .claude/hooks
   ```

2. Create `.claude/hooks/session-start.sh`:
   ```bash
   cat > .claude/hooks/session-start.sh << 'EOF'
#!/bin/bash
# Auto-install bd in every Claude Code for Web session

# Install bd globally from npm
npm install -g @beads/bd

# Initialize bd if not already done
if [ ! -d .beads ]; then
  bd init --quiet
fi

# Show current work
echo ""
echo "📋 Ready work:"
bd ready --limit 5 || echo "No ready work found"
EOF

   chmod +x .claude/hooks/session-start.sh
   ```

3. Update `.claude/settings.local.json` to add hooks:

   If the file doesn't exist, create it:
   ```bash
   cat > .claude/settings.local.json << 'EOF'
{
  "permissions": {
    "allow": [
      "Bash(bd:*)",
      "Bash(bv:*)"
    ],
    "deny": []
  },
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ],
    "PreCompact": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bd prime"
          }
        ]
      }
    ]
  }
}
EOF
   ```

   If it already exists, merge the `hooks` section and add `Bash(bd:*)`, `Bash(bv:*)` to permissions.

### 4. Verify Setup

```bash
# Check bd is initialized
bd info

# Check for ready work
bd ready

# Check git hooks are installed
ls -la .git/hooks/ | grep -E '(pre-commit|post-merge)'

# Check Claude Code hooks
cat .claude/settings.local.json | grep -A 5 hooks
```

## What Each Hook Does

### Git Hooks (installed in `.git/hooks/`)

- **`pre-commit`**: Before each commit, exports bd database to `.beads/issues.jsonl` and stages it
- **`post-merge`**: After `git pull` or merge, imports changes from `.beads/issues.jsonl` into local database

### Claude Code Hooks (configured in `.claude/settings.local.json`)

- **`SessionStart`**: Runs `bd prime` when starting a Claude Code session
  - Loads issue context into agent's memory automatically

- **`PreCompact`**: Runs `bd prime` before compacting conversation history
  - Ensures bd context is refreshed before memory compaction

### Web Session Hook (`.claude/hooks/session-start.sh`)

- For Claude Code for Web (browser version)
- Auto-installs `@beads/bd` via npm
- Runs `bd init` if needed
- Shows ready work at session start

## Quick Reference

Once set up, use these commands:

```bash
# See available work
bd ready

# Create an issue
bd create "Task description" -t task -p 1 --json

# Update issue status
bd update <issue-id> --status in_progress

# Close issue
bd close <issue-id> --reason "Completed successfully"

# Sync to git (happens automatically, but can be forced)
bd sync

# Use bv for graph analysis
bv --robot-insights
bv --robot-plan
```

## Documentation

- **Quick Reference**: `./bd_bv_cheatsheet.md`
- **Full Overview**: `./bd-bv-overview.md`
- **bd Reference**: `./bd.md`
- **bv Reference**: `./bv.md`
- **Multi-agent Workflows**: `./instructions.md`

## Troubleshooting

### bd not found
```bash
# Add to PATH (if install script didn't do it)
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### Git hooks not working
```bash
# Reinstall hooks
bd init --quiet
```

### Claude Code hooks not firing
- Check `.claude/settings.local.json` syntax (must be valid JSON)
- Restart Claude Code session
- Check permissions include `Bash(bd:*)`

### Issues not syncing between machines
```bash
# Force sync
bd sync

# Check git status
git status .beads/
```

## Support

For more help:
- bd documentation: https://github.com/steveyegge/beads
- bv documentation: https://github.com/Dicklesworthstone/beads_viewer
- Team documentation: `./docs-internal/bd-bv/`
