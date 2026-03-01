# Adding Examples and Example Sections

## Overview

Examples live in two places:
- `./examples/` — the primary location for root-level examples (groups `A*`–`Z*`)
- `./packages/<name>/examples/` — package-specific examples (instructor, polyglot, addons, etc.)

Two config files in `./config/` control discovery and grouping:
- `examples.yaml` — lists which directories are scanned as sources
- `examples-groups.yaml` — maps source paths to named groups and subgroups

The hub reads these from `./config/` at the project root. The copies under `packages/hub/config/` are mirrors kept in sync manually.

---

## Adding a Single Example

1. **Create a directory** under the appropriate source path, e.g. `examples/D20_Sandbox/MyExample/`.

2. **Add `run.php`** with a YAML front-matter header followed by a markdown overview and a fenced PHP code block:

```php
---
title: 'My Example Title'
docname: 'my_example_title'
id: 'dXXX'
---
## Overview

Brief description of what this example demonstrates.

## Example

```php
<?php
require 'examples/boot.php';

// ... example code ...
?>
```
```

Front-matter fields:

| Field     | Required | Description                                                      |
|-----------|----------|------------------------------------------------------------------|
| `title`   | yes      | Human-readable title shown in listings and docs                  |
| `docname` | yes      | Unique slug used as the doc URL path (snake_case)                |
| `id`      | yes      | Short hex ID for stable `xNNNN` short-link lookup (e.g. `d210`) |
| `order`   | no       | Integer sort order within a subgroup (lower = earlier)           |

Both `docname` and `id` must be **unique across all examples**.

3. **No further steps** if the directory is already covered by an existing subgroup rule in `config/examples-groups.yaml`. Run `composer hub list` to confirm the example appears under the expected group.

---

## Adding a New Subgroup to an Existing Section

Edit `config/examples-groups.yaml` and add a `subgroups` entry under the relevant group:

```yaml
- id: agents
  title: Agents and Agent Controllers
  subgroups:
    # ... existing subgroups ...
    - id: my_new_section
      title: My New Section
      include:
        - source: root
          path: D25_MyNewSection
```

The `source` value must match a `package` key in `config/examples.yaml`. The `path` is matched as a prefix against the relative example path (e.g. `D25_MyNewSection/SomeExample`).

---

## Adding a New Top-Level Group

Append a new entry to the `groups` list in `config/examples-groups.yaml`:

```yaml
- id: my_group
  title: My Group
  subgroups:
    - id: my_subgroup
      title: My Subgroup
      include:
        - source: root
          path: E01_MyGroup
```

Groups appear in the hub listing and generated docs in the order they are defined in the file.

---

## Adding Examples from a New Package Source

If examples live in a package that is not yet registered, add it to `config/examples.yaml`:

```yaml
sources:
  # ... existing sources ...
  - package: my-package
    path: ./packages/my-package/examples
```

Then reference `source: my-package` in the group rules as normal.

---

## Verifying

After any change, run:

```bash
composer hub list
```

Confirm the new examples appear under the expected `tab / subgroup` column. If they show `examples / <raw-dir>` instead, the subgroup rule is not matching — check that `source` and `path` in `examples-groups.yaml` are correct.
