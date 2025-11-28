# Internal Documentation

This directory contains documentation for the development team and AI agents working on instructor-php.

## Contents

### taskmaster.md - Task Master AI Integration

Complete guide for Task Master AI integration, including:
- Essential commands and workflow
- MCP integration setup
- Claude Code best practices
- Task structure and management

### bd-bv/ - Issue Tracking System

Documentation for the bd/bv issue tracking and graph analysis system.

**Start here:**
- **[SETUP.md](bd-bv/SETUP.md)** - Step-by-step setup guide for new developers
- **[bd_bv_cheatsheet.md](bd-bv/bd_bv_cheatsheet.md)** - Quick reference for daily use

**Setup script:**
- **[setup-claude-hooks.sh](bd-bv/setup-claude-hooks.sh)** - Automated Claude Code hooks configuration

**Reference documentation:**
- **[bd-bv-overview.md](bd-bv/bd-bv-overview.md)** - Complete system overview
- **[bd.md](bd-bv/bd.md)** - Full bd CLI reference
- **[bv.md](bd-bv/bv.md)** - Full bv TUI and graph analysis reference
- **[instructions.md](bd-bv/instructions.md)** - Multi-agent collaboration patterns

### development/ - Development Documentation

Developer-specific documentation and guides:

- **[SCRIPTS.md](development/SCRIPTS.md)** - Overview of utility scripts in `./scripts/`

### testing/ - Testing Documentation

Testing infrastructure and quality assurance:

- **[TEST_MATRIX.md](testing/TEST_MATRIX.md)** - Local test matrix runner documentation

## Quick Start for New Developers

1. **Install tools:**
   ```bash
   # bd (issue tracker)
   curl -fsSL https://raw.githubusercontent.com/steveyegge/beads/main/scripts/install.sh | bash

   # bv (graph analyzer)
   curl -fsSL https://raw.githubusercontent.com/Dicklesworthstone/beads_viewer/main/install.sh | bash
   ```

2. **Initialize in repo:**
   ```bash
   cd instructor-php
   bd init --quiet
   ```

3. **Configure Claude Code:**
   ```bash
   ./docs-internal/bd-bv/setup-claude-hooks.sh
   ```

4. **Verify setup:**
   ```bash
   bd ready
   ```

5. **Read the cheatsheet:**
   ```bash
   cat docs-internal/bd-bv/bd_bv_cheatsheet.md
   ```

## Adding New Documentation

When adding documentation to this directory:

1. Use clear, descriptive filenames
2. Include a brief summary at the top of each file
3. Update this README with links to new content
4. Keep documentation up-to-date with code changes

## Why This Directory?

This directory is committed to git (unlike `.taskmaster/` or `.claude/`) but is separate from public-facing `docs/` which contains product documentation.

Use this for:
- Team workflows and processes
- Development tool documentation
- AI agent instructions and guides
- Internal architecture decisions
- Setup and configuration guides

Do NOT use for:
- Public API documentation (use `docs/`)
- User-facing guides (use `docs/`)
- Package-specific docs (use `packages/*/README.md`)
