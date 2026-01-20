---
title: 'Examples Configuration'
description: 'Configure example sources and cookbook grouping for Hub and Docgen'
---

This project uses two config files to control how examples are discovered and how the cookbook navigation is grouped. These configs are shared by `composer hub` and `composer docs`.

## Example Sources

File: `config/examples.yaml`

Purpose: define where examples live (multiple roots), and the order in which sources are scanned.

```yaml
version: 1
sources:
  - package: instructor
    path: ./packages/instructor/examples
  - package: polyglot
    path: ./packages/polyglot/examples
  - package: root
    path: ./examples
```

Notes:
- The `sources` list order is the primary ordering for examples.
- Each `package` becomes the source id (used by grouping rules).
- If the file is missing, the system falls back to `./examples`.
- Missing directories are ignored (no warnings).

## Example Groups and Ordering

File: `config/examples-groups.yaml`

Purpose: define cookbook groups, subgroups, and the ordering of examples inside them.

```yaml
version: 1
groups:
  - id: structured_outputs
    title: Structured Outputs
    subgroups:
      - id: basics
        title: Basics
        include:
          - source: instructor
            path: A01_Basics
```

Notes:
- Group order follows the sequence in `groups`.
- Subgroup order follows the sequence in `subgroups`.
- Group titles are flattened as `Parent \\ Child` for navigation labels.
- Example paths are matched against the example directory relative to the source root.

### Matching rules

Rules support simple patterns:
- Exact path: `A01_Basics`
- Prefix wildcard: `C07_Misc/Http*`
- Any path: `*`

Rules can optionally target a specific source:
```yaml
include:
  - source: http-client
    path: '*'
```

### Exclusions

Use `exclude` to carve out subsets:
```yaml
exclude:
  - source: root
    path: C07_Misc/Http*
```

## Navigation Behavior

- MkDocs supports nested navigation. We flatten group titles so the cookbook stays consistent across outputs.
- Mintlify supports only one level of grouping in this pipeline, so flattened titles are required.

## Migration Tip

Keep `examples/boot.php` at the project root. Examples can live in package directories while still including the shared bootstrap.
