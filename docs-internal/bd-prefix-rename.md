# How to Rename BD Issue Prefixes

A guide for project teams on correctly changing issue prefixes in Beads (bd) issue tracker.

## Understanding Issue Prefixes

Issue prefixes in bd are stored in **two places**:

1. **`.beads/config.yaml`** - Used during `bd init`, defines default for new repos
2. **Database config** - The actual prefix used by the running system

**Critical:** The config.yaml setting is only read during initialization. Changing it won't affect an existing repository.

## The Problem

When you change a project's issue prefix, you get mismatches:
- Database expects new prefix (e.g., `qmd-`)
- JSONL contains old issues (e.g., `refix-`)
- `bd sync` fails with prefix mismatch errors
- Import/export operations fail

## The Correct Process

### Step 1: Check Current Prefix

```bash
# See what prefix is currently in use
bd config get issue_prefix

# Alternative: check config directly
bd config list | grep issue_prefix
```

### Step 2: Update Database Prefix

```bash
# Set new prefix in database (this is the critical step)
bd config set issue_prefix <new-prefix>

# Verify it changed
bd config get issue_prefix
```

### Step 3: Handle Existing Issues

You have two options:

#### Option A: Archive and Recreate (Recommended for small issue counts)

```bash
# 1. Export existing issues for reference
bd export > backup-issues-$(date +%Y%m%d).jsonl

# 2. Close all existing issues with old prefix
bd list --status=open --json | jq -r '.[].id' | xargs -I {} bd close {}

# 3. Create new issues with new prefix
# (manually recreate important issues or use a script)

# 4. Fix JSONL sync
bd export > .beads/issues.jsonl
```

#### Option B: Database Migration (For large issue counts)

```bash
# WARNING: Direct database manipulation - backup first!
cp .beads/beads.db .beads/beads.db.backup

# Use sqlite3 to rename prefixes in database
sqlite3 .beads/beads.db <<EOF
UPDATE issues
SET id = REPLACE(id, 'old-prefix-', 'new-prefix-')
WHERE id LIKE 'old-prefix-%';

UPDATE dependencies
SET issue_id = REPLACE(issue_id, 'old-prefix-', 'new-prefix-'),
    depends_on_id = REPLACE(depends_on_id, 'old-prefix-', 'new-prefix-')
WHERE issue_id LIKE 'old-prefix-%'
   OR depends_on_id LIKE 'old-prefix-%';
EOF

# Export clean JSONL
bd export > .beads/issues.jsonl

# Verify
bd list --status=open
```

### Step 4: Sync and Commit

```bash
# Try syncing (may show worktree errors but should work)
bd sync

# If sync fails with JSONL conflicts, force export:
bd export > .beads/issues.jsonl

# If using git-tracked beads (not ignored), commit:
git add .beads/issues.jsonl .beads/config.yaml
git commit -m "Rename issue prefix from old-prefix to new-prefix"
git push
```

## Common Issues and Fixes

### Issue: "Prefix mismatch detected"

**Symptom:**
```
Error: prefix mismatch detected: database uses 'new-'
but found issues with prefixes: [old- (18 issues)]
```

**Fix:**
```bash
# Export fresh JSONL from database
bd export > .beads/issues.jsonl

# Try sync again
bd sync
```

### Issue: "Worktree already checked out"

**Symptom:**
```
Error pulling from sync branch: failed to create worktree
fatal: 'main' is already checked out
```

**Fix:**
This is a known bd sync issue when the branch is already checked out. It's usually harmless if you see "No changes to commit" afterward. If not:

```bash
# Manual sync: export and commit
bd export > .beads/issues.jsonl
git add .beads/issues.jsonl
git commit -m "Update beads issues"
git push
```

### Issue: ".beads directory ignored, can't commit"

**Symptom:**
```
The following paths are ignored by one of your .gitignore files:
.beads
```

**Fix:**
This is intentional if you chose to ignore .beads/. In this case:
- Beads data stays local
- Don't use `bd sync` (it assumes git-tracked beads)
- Team members manage their own beads
- Consider removing .beads/ from .gitignore if team collaboration needed

## Best Practices

### 1. Set Prefix Early

Choose your prefix during `bd init` or immediately after:
```bash
bd init
bd config set issue_prefix myproject
```

### 2. Keep config.yaml In Sync

Even though config.yaml isn't used after init, keep it updated for documentation:
```yaml
# .beads/config.yaml
issue-prefix: "myproject"
```

### 3. Backup Before Renaming

```bash
# Backup database
cp .beads/beads.db .beads/beads.db.backup

# Export issues
bd export > backup-issues-$(date +%Y%m%d).jsonl

# Backup JSONL
cp .beads/issues.jsonl .beads/issues.jsonl.backup
```

### 4. Document the Change

When renaming prefix in a team project:
```bash
# Create a clear commit message
git commit -m "Change issue prefix from old-prefix to new-prefix

All issues have been migrated to the new prefix format.
Old closed issues are archived in backup-issues-YYYYMMDD.jsonl

BREAKING: Update any external references to use new prefix format."
```

### 5. Verify After Rename

```bash
# Check stats
bd stats

# List open issues (verify prefix)
bd list --status=open

# Check for orphaned issues
bd list --status=closed | grep -v "^new-prefix-"

# Test basic operations
bd create --title="Test issue" --type=task
bd close <test-issue-id>
```

## Automation Script

For teams that need to rename frequently, here's a helper script:

```bash
#!/bin/bash
# bd-rename-prefix.sh

set -e

OLD_PREFIX=$1
NEW_PREFIX=$2

if [ -z "$OLD_PREFIX" ] || [ -z "$NEW_PREFIX" ]; then
    echo "Usage: $0 <old-prefix> <new-prefix>"
    exit 1
fi

echo "🔄 Renaming BD issue prefix: $OLD_PREFIX → $NEW_PREFIX"

# Backup
echo "📦 Creating backup..."
cp .beads/beads.db ".beads/beads.db.backup-$(date +%Y%m%d-%H%M%S)"
bd export > "backup-issues-$(date +%Y%m%d-%H%M%S).jsonl"

# Update database config
echo "⚙️  Updating database config..."
bd config set issue_prefix "$NEW_PREFIX"

# Update database IDs
echo "🔧 Migrating issue IDs in database..."
sqlite3 .beads/beads.db <<EOF
BEGIN TRANSACTION;

UPDATE issues
SET id = REPLACE(id, '${OLD_PREFIX}-', '${NEW_PREFIX}-')
WHERE id LIKE '${OLD_PREFIX}-%';

UPDATE dependencies
SET issue_id = REPLACE(issue_id, '${OLD_PREFIX}-', '${NEW_PREFIX}-'),
    depends_on_id = REPLACE(depends_on_id, '${OLD_PREFIX}-', '${NEW_PREFIX}-')
WHERE issue_id LIKE '${OLD_PREFIX}-%'
   OR depends_on_id LIKE '${OLD_PREFIX}-%';

COMMIT;
EOF

# Update config.yaml
echo "📝 Updating config.yaml..."
sed -i "s/issue-prefix: \"$OLD_PREFIX\"/issue-prefix: \"$NEW_PREFIX\"/g" .beads/config.yaml
sed -i "s/# issue-prefix: \"\"/issue-prefix: \"$NEW_PREFIX\"/g" .beads/config.yaml

# Export clean JSONL
echo "💾 Exporting clean JSONL..."
bd export > .beads/issues.jsonl

# Verify
echo "✅ Verifying..."
bd stats
echo ""
echo "✅ Prefix renamed successfully!"
echo ""
echo "Next steps:"
echo "  1. Review with: bd list --status=open"
echo "  2. Test with: bd create --title='Test' --type=task"
echo "  3. Commit with: git add .beads/ && git commit -m 'Rename prefix: $OLD_PREFIX → $NEW_PREFIX'"
```

Save as `bd-rename-prefix.sh`, make executable, and use:
```bash
chmod +x bd-rename-prefix.sh
./bd-rename-prefix.sh old-prefix new-prefix
```

## When to Rename vs. Start Fresh

### Rename if:
- ✅ Project is established with important issue history
- ✅ Many issues with dependencies and context
- ✅ Team references issues in commits/PRs
- ✅ You want to maintain audit trail

### Start Fresh if:
- ✅ New project, few issues
- ✅ Issues lack significant context
- ✅ No external references to issue IDs
- ✅ Clean slate is acceptable

To start fresh:
```bash
# Backup old beads
mv .beads .beads.old

# Reinitialize
bd init --prefix=new-prefix

# Archive reference
tar czf beads-archive-$(date +%Y%m%d).tar.gz .beads.old
rm -rf .beads.old
```

## Related Configuration

### Multi-repo Setup

If using bd with multiple repos, prefix helps distinguish issues:

```yaml
# .beads/config.yaml
issue-prefix: "myapp"

repos:
  primary: "."
  additional:
    - ~/beads-planning  # Might use "plan-" prefix
    - ~/work-tasks      # Might use "work-" prefix
```

### Integration with External Systems

When syncing with Jira/Linear/GitHub, consistent prefixes help:

```bash
# Configure integration
bd config set github.org myorg
bd config set github.repo myrepo
bd config set issue_prefix myrepo  # Match repo name
```

## Troubleshooting Checklist

If things go wrong during prefix rename:

- [ ] Restore from backup: `cp .beads/beads.db.backup .beads/beads.db`
- [ ] Check prefix in database: `bd config get issue_prefix`
- [ ] Check prefix in config.yaml: `grep issue-prefix .beads/config.yaml`
- [ ] Export fresh JSONL: `bd export > .beads/issues.jsonl`
- [ ] Verify with: `bd list --status=open`
- [ ] Check stats: `bd stats`
- [ ] Test create: `bd create --title="Test" --type=task`

## Summary

**The key insight:** The issue prefix lives in the database config, not just the config.yaml file.

**The correct approach:**
1. Update database: `bd config set issue_prefix <new>`
2. Migrate issues (close/recreate OR database UPDATE)
3. Export clean JSONL: `bd export > .beads/issues.jsonl`
4. Verify and commit

**Don't:**
- ❌ Just edit config.yaml and expect it to work
- ❌ Try to manually edit JSONL files
- ❌ Use `--rename-on-import` without understanding the consequences
- ❌ Skip backups before renaming

**Do:**
- ✅ Backup before making changes
- ✅ Use `bd config set` to change prefix
- ✅ Export fresh JSONL after migration
- ✅ Verify thoroughly before committing
