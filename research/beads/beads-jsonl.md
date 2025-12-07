# Beads JSONL File Format Specification

## Overview

The **beads JSONL format** is a JSON Lines format (one JSON object per line) used to store and synchronize issues across the Beads issue tracking system. The default filename is `issues.jsonl` (previously `beads.jsonl` for legacy support) stored in `.beads/` directory of a repository.

## Core Issue Structure

Each line in the JSONL file is a complete JSON object representing a single issue.

### Required Fields

| Field | Type | Description | Validation |
|-------|------|-------------|-----------|
| `id` | string | Unique issue identifier | Format: `{prefix}-{id}` (e.g., `bd-03r`, `myproject-a3f2dd`). Can be sequential (bd-1, bd-2) or hash-based (bd-a3f2dd, bd-7k9p1x). Must match configured prefix. |
| `title` | string | Issue title/summary | Required, max 500 characters |
| `description` | string | Detailed issue description | Can be empty string, supports markdown |
| `status` | string | Current state of the issue | Must be one of: `"open"`, `"in_progress"`, `"blocked"`, `"closed"`, or custom status (if configured) |
| `priority` | integer | Priority level | Must be 0-4: 0 (Critical), 1 (High), 2 (Medium/default), 3 (Low), 4 (Backlog) |
| `issue_type` | string | Category of work | Must be one of: `"bug"`, `"feature"`, `"task"`, `"epic"`, `"chore"` |
| `created_at` | string (ISO 8601) | Timestamp when created | Format: `2025-11-25T14:56:49.13027-08:00` or `2025-11-25T14:56:49Z` |
| `updated_at` | string (ISO 8601) | Timestamp of last update | Same format as created_at |

### Optional Fields

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `assignee` | string | Person assigned to work | Username/email, omitted if not assigned |
| `design` | string | Design notes or specifications | Omitted if not provided |
| `acceptance_criteria` | string | Conditions for completion | Omitted if not defined |
| `notes` | string | Additional notes | Omitted if not present |
| `estimated_minutes` | integer | Time estimate in minutes | Omitted if not estimated, must be non-negative if present |
| `closed_at` | string (ISO 8601) | When the issue was closed | **MUST be present if status="closed"**, **MUST be absent if status!="closed"** |
| `close_reason` | string | Reason for closing | Omitted if not closed |
| `external_ref` | string | Reference to external system | e.g., "gh-123", "jira-ABC-456", "https://github.com/owner/repo/issues/123" |
| `labels` | array of strings | Tags/labels for categorization | Omitted if empty, common labels: "urgent", "documentation", "help-wanted" |
| `compaction_level` | integer | Semantic compaction level | 0-10, used for memory decay feature |
| `compacted_at` | string (ISO 8601) | When the issue was compacted | Omitted if not compacted |
| `compacted_at_commit` | string | Git commit hash of compaction | Omitted if not compacted |
| `original_size` | integer | Original size before compaction | Omitted if not compacted |
| `dependencies` | array of Dependency objects | Relationships to other issues | Omitted if empty |
| `comments` | array of Comment objects | Comments on the issue | Omitted if empty |

## Dependency Object Structure

Each dependency is a complete object within the `dependencies` array:

```json
{
  "issue_id": "bd-03r",
  "depends_on_id": "bd-4t7",
  "type": "blocks",
  "created_at": "2025-11-25T14:56:49.13027-08:00",
  "created_by": "alice"
}
```

| Field | Type | Description | Valid Values |
|-------|------|-------------|--------------|
| `issue_id` | string | ID of the issue with the dependency | Format: `{prefix}-{id}` |
| `depends_on_id` | string | ID of the issue being depended on | Format: `{prefix}-{id}` |
| `type` | string | Type of dependency relationship | `"blocks"`, `"related"`, `"parent-child"`, `"discovered-from"` |
| `created_at` | string (ISO 8601) | When dependency was created | ISO 8601 timestamp |
| `created_by` | string | Who created the dependency | Username of the creator |

**Special handling during import:** The `issue_id` field can be empty string `""` during import, and the system will fill it automatically with the ID of the current issue being imported.

## Comment Object Structure

Each comment is an object within the `comments` array:

```json
{
  "id": 12345,
  "issue_id": "bd-03r",
  "author": "alice",
  "text": "This is a comment",
  "created_at": "2025-11-25T14:56:49.13027-08:00"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique comment identifier (auto-generated) |
| `issue_id` | string | ID of the issue being commented on |
| `author` | string | Username of the comment author |
| `text` | string | The comment text content |
| `created_at` | string (ISO 8601) | Timestamp when comment was created |

## Complete Example Entry

```json
{
  "id": "bd-03r",
  "title": "Document deletions manifest in AGENTS.md and README",
  "description": "Parent: bd-imj\n\n## Task\nAdd documentation about the deletions manifest feature.",
  "design": "Use markdown sections",
  "acceptance_criteria": "- AGENTS.md updated\n- README.md mentions deletions.jsonl\n- New docs/deletions.md created",
  "notes": "See bd-imj for context",
  "status": "closed",
  "priority": 2,
  "issue_type": "task",
  "assignee": "alice",
  "estimated_minutes": 120,
  "created_at": "2025-11-25T14:56:49.13027-08:00",
  "updated_at": "2025-11-25T15:17:23.145944-08:00",
  "closed_at": "2025-11-25T15:17:23.145944-08:00",
  "close_reason": "Completed and merged to main",
  "external_ref": "gh-441",
  "labels": ["documentation", "process"],
  "dependencies": [
    {
      "issue_id": "bd-03r",
      "depends_on_id": "bd-imj",
      "type": "parent-child",
      "created_at": "2025-11-25T14:56:49.13027-08:00",
      "created_by": "daemon"
    }
  ],
  "comments": [
    {
      "id": 1,
      "issue_id": "bd-03r",
      "author": "bob",
      "text": "Good catch on the deletion manifest!",
      "created_at": "2025-11-25T15:00:00.000000-08:00"
    }
  ]
}
```

## Minimal Required Entry

The absolute minimum fields needed for a valid issue:

```json
{
  "id": "bd-1",
  "title": "Fix login bug",
  "description": "Users can't log in",
  "status": "open",
  "priority": 2,
  "issue_type": "bug",
  "created_at": "2025-12-02T00:00:00Z",
  "updated_at": "2025-12-02T00:00:00Z"
}
```

## Enum Values Reference

### Status Values
- `"open"` - Issue is open and ready to work
- `"in_progress"` - Someone is actively working on it
- `"blocked"` - Issue is blocked waiting for something
- `"closed"` - Issue is complete

### Issue Type Values
- `"bug"` - Defect or problem to fix
- `"feature"` - New capability to add
- `"task"` - Work item or chore
- `"epic"` - Large feature or initiative
- `"chore"` - Maintenance or refactoring

### Dependency Type Values
- `"blocks"` - This issue blocks another (this -> depends_on_id)
- `"related"` - This issue is related to another
- `"parent-child"` - Parent-child relationship (epic-to-task)
- `"discovered-from"` - Automatically discovered from git history

### Priority Values
- `0` - Critical
- `1` - High
- `2` - Medium (default)
- `3` - Low
- `4` - Backlog

## Import/Export Behavior

### During Export (`bd export`)
- All JSON fields with values are included
- Fields with empty/zero values follow `omitempty` rules
- Issues are sorted by ID for consistent diffs
- Dependencies, labels, and comments are fully populated

### During Import (`bd import`)
- `id` and `created_at`/`updated_at` are required
- For dependencies, `issue_id` can be empty and is auto-filled
- Existing issues (same ID) are updated with incoming data
- Timestamps must be valid ISO 8601 format
- The system performs collision detection and validation

## Timestamp Format

All timestamps use ISO 8601 format with timezone information:
- Preferred: `2025-11-25T14:56:49.13027-08:00` (with nanosecond precision and offset)
- Also accepted: `2025-11-25T14:56:49Z` (UTC with Z suffix)
- Also accepted: `2025-11-25T14:56:49+00:00` (UTC with offset)

The system normalizes all timestamps to a consistent format internally.

## Hash-Based IDs (v0.20.1+)

Modern beads uses content-hash-based IDs instead of sequential numbers:

### Components of a hash ID
- `prefix` - Project prefix (default: "bd")
- `hash` - SHA256 hash of: `title|description|creator|timestamp|nonce`
- `length` - Encoded length: 3-8 characters (default: 6)

### Examples
- Sequential: `bd-1`, `bd-2`, `bd-3`
- Hash-based: `bd-a3f2dd`, `bd-7k9p1x`, `bd-f14c3e`

### Collision handling
If the same content is created multiple times, the nonce counter increments or the hash length extends to ensure uniqueness.

## File Location and Naming

- **Primary location:** `.beads/issues.jsonl` (current default)
- **Legacy name:** `.beads/beads.jsonl` (still supported)
- **Deletions manifest:** `.beads/deletions.jsonl` (tracks deleted issues)

The system checks for both filenames and prefers `issues.jsonl` if both exist.

## Validation Rules

1. **ID Validation:** Must match the configured `issue_prefix` from `config.yaml`
2. **Status-Closed Invariant:** If status is "closed", `closed_at` MUST be present. If status is not "closed", `closed_at` MUST be absent
3. **Priority Range:** 0-4 (validated on import)
4. **Title Required:** Max 500 characters, non-empty
5. **Timestamps:** Must be valid ISO 8601 format
6. **Description:** Can be empty string, but field is required
7. **Dependencies:** Must reference valid issue IDs, types must be valid enum values
8. **Estimated Minutes:** If present, must be non-negative

## Special Fields NOT in JSONL

The following fields exist in the database but are NOT exported to JSONL:
- `ContentHash` - Internal SHA256 of issue content (internal use only)
- `SourceRepo` - Multi-repo identifier (internal use only)

These are managed entirely by the system.
