# Beads (bd/bv) File Structure and Standards

## Official Standard Directory Structure

According to the [official beads documentation](https://github.com/steveyegge/beads), beads uses a `.beads/` directory in the project root with specific file organization.

### Files Committed to Git (Source of Truth)

These files should be tracked in version control and synced across machines:

- **`.beads/issues.jsonl`** - The source of truth for issue data in JSONL format
- **`.beads/metadata.json`** - Database metadata and configuration
- **`.beads/config.yaml`** - Repository configuration template
- **`.beads/README.md`** - Documentation about beads for repository visitors
- **`.gitattributes`** - Configures git merge driver for intelligent JSONL merging

### Local-Only Files (Gitignored)

These files remain local to each machine and should NOT be committed:

- **`.beads/beads.db*`** - SQLite cache files that auto-sync with JSONL
- **`.beads/daemon.lock`** - Daemon lock file
- **`.beads/daemon.log`** - Daemon log file
- **`.beads/daemon.pid`** - Daemon process ID
- **`.beads/bd.sock`** - Daemon communication socket
- **`.beads/beads.base.*`** - Temporary merge artifacts during git operations

### Auto-Sync Mechanism

The system maintains automatic synchronization:
- **SQLite → JSONL**: After CRUD operations (5-second debounce)
- **JSONL → SQLite**: When JSONL is newer (e.g., after `git pull`)

## Our Current Setup Verification

✅ **Correctly Configured** - Our setup matches the official standard:

```bash
$ ls -la .beads/
# Files committed to git:
-rw-r--r--  issues.jsonl        # ✅ Source of truth
-rw-------  metadata.json       # ✅ Database metadata
-rw-------  config.yaml         # ✅ Repository config
-rw-r--r--  README.md          # ✅ Documentation

# Files properly gitignored:
-rw-r--r--  beads.db*          # ✅ SQLite cache
-rw-------  daemon.*           # ✅ Daemon runtime
srw-------  bd.sock            # ✅ Communication socket
-rw-------  .gitignore         # ✅ Local gitignore rules
```

### Gitignore Configuration

Our `.beads/.gitignore` properly excludes local files:

```gitignore
# SQLite databases (local cache)
*.db
*.db?*
*.db-journal
*.db-wal
*.db-shm

# Daemon runtime files
daemon.lock
daemon.log
daemon.pid
bd.sock

# Merge artifacts (temporary)
beads.base.*

# Keep these (source of truth)
!issues.jsonl
!metadata.json
!config.yaml
```

## Key Design Principles

1. **Git-backed distributed database** - No server required
2. **Hash-based IDs** - Collision-resistant for multi-agent workflows
3. **Local SQLite cache** - Fast operations with JSONL sync
4. **Zero setup** - `bd init` creates project-local database
5. **AI agent friendly** - Designed specifically for coding agents

## Initialization Process

```bash
# Standard initialization
bd init

# Creates:
# - .beads/ directory with proper structure
# - Local SQLite database
# - Git hooks for auto-sync
# - Merge drivers for JSONL conflict resolution
```

## References

- **Official Repository**: [steveyegge/beads](https://github.com/steveyegge/beads)
- **Introduction Article**: [Introducing Beads: A coding agent memory system](https://steve-yegge.medium.com/introducing-beads-a-coding-agent-memory-system-637d7d92514a)
- **Technical Deep Dive**: [The Beads Revolution: How I Built The TODO System That AI Agents Actually Want to Use](https://steve-yegge.medium.com/the-beads-revolution-how-i-built-the-todo-system-that-ai-agents-actually-want-to-use-228a5f9be2a9)
- **Local Documentation**: `docs-internal/bd-bv/bd_bv_cheatsheet.md`

---

*Last updated: 2025-11-28*
*Verified against beads version: Current as of documentation review*