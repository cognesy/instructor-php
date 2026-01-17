# Recommended Approach: Incremental Improvement

## Summary

After consulting architecture, DX, and maintainability experts, we recommend an **incremental approach** that delivers value without the risks of full modularization.

## Phased Approach

### Phase 0: Cleanup (Immediate)
**Goal**: Remove existing duplication before adding any complexity.

**Actions**:
1. Delete `/packages/hub/examples/` (duplicate of `/examples/`)
2. Update any references to use primary `/examples/` location

**Effort**: 1 hour | **Risk**: Very low

---

### Phase 1: Externalize Configuration
**Goal**: Move hardcoded mapping from code to config file.

**Current** (in `Example.php`):
```php
$mapping = [
    'A01_Basics' => ['tab' => 'instructor', 'name' => 'basics', ...],
    // 16 more entries
];
```

**Proposed** (in `/config/examples.yaml`):
```yaml
groups:
  A01_Basics:
    tab: instructor
    name: basics
    title: 'Cookbook \ Instructor \ Basics'

  A02_Advanced:
    tab: instructor
    name: advanced
    title: 'Cookbook \ Instructor \ Advanced'

  # ... remaining groups

  C07_Misc:
    tab: prompting
    name: misc
    title: 'Cookbook \ Prompting \ Miscellaneous'

# Optional: Define allowed tabs
tabs:
  - instructor
  - polyglot
  - prompting
```

**Changes needed**:
- Create `/config/examples.yaml`
- Modify `Example::loadExample()` to read config instead of hardcoded array
- Add config loading to `Hub` class

**Benefits**:
- No file moves
- No front matter updates
- Configuration-driven (easy to modify)
- Zero migration risk

**Effort**: 4-6 hours | **Risk**: Very low

---

### Phase 2: Enhanced Front Matter (Optional)
**Goal**: Allow examples to override config via front matter.

**Extended schema** (all fields optional):
```yaml
---
title: 'Basic use'
docname: 'basic_use'
tab: 'instructor'          # Override group default
section: 'basics'          # Override group name
weight: 10                 # Custom ordering
tags: ['getting-started']  # For future filtering
---
```

**Precedence**:
1. Front matter (if present)
2. Config file mapping
3. Legacy hardcoded fallback

**Benefits**:
- Gradual adoption
- Per-example customization when needed
- Backward compatible

**Effort**: 4-6 hours | **Risk**: Low

---

### Phase 3: Virtual Package View (If Needed)
**Goal**: Enable package-centric CLI without moving files.

**New capability**:
```bash
composer hub list instructor        # Show only instructor examples
composer hub run instructor:Basic   # Namespaced reference
```

**Implementation**:
```php
class ExampleRepository {
    public function forPackage(string $package): self {
        return new self($this->baseDir, $this->config, $package);
    }

    public function getAllExamples(): array {
        $examples = $this->discoverAll();
        if ($this->packageFilter) {
            $examples = array_filter($examples,
                fn($e) => $e->tab === $this->packageFilter
            );
        }
        return $examples;
    }
}
```

**Benefits**:
- Package-centric API without physical restructure
- `polyglot:inference` syntax for clarity
- Tab autocomplete in shell

**Effort**: 8-10 hours | **Risk**: Low

---

### Phase 4: Selective Physical Migration (Future)
**Goal**: Move specific examples to package directories only when there's clear benefit.

**When to consider**:
- New packages with distinct example sets
- Examples tightly coupled to a single package's internals
- When package is published separately

**When NOT to migrate**:
- Cross-package examples
- Prompting examples (no package)
- Working examples with no issues

**If migrating**:
1. Create `/packages/{pkg}/examples/` for that package only
2. Keep legacy location working via symlinks
3. Update front matter with `package` field
4. Run validation before and after

---

## Comparison

| Approach | Effort | Risk | Benefit |
|----------|--------|------|---------|
| Full modularization | ~75 hours | High | "Clean" architecture |
| **Recommended incremental** | ~15-20 hours | Low | Same benefits, less risk |
| Do nothing | 0 hours | None | Status quo (works fine) |

## Decision Tree

```
Is current system causing problems?
├── No → Phase 0 only (cleanup duplication)
└── Yes → What kind of problems?
    ├── "Can't easily change group mapping" → Phase 1 (config file)
    ├── "Need per-example customization" → Phase 2 (front matter)
    ├── "Want package-centric CLI" → Phase 3 (virtual view)
    └── "Need physical separation for publishing" → Phase 4 (selective migration)
```

## Files to Modify

### Phase 0
- Delete `/packages/hub/examples/` directory

### Phase 1
| File | Change |
|------|--------|
| `/config/examples.yaml` | New file - group mapping |
| `/packages/hub/src/Data/Example.php` | Load config instead of hardcoded array |
| `/packages/hub/src/Hub.php` | Wire config loading |

### Phase 2
| File | Change |
|------|--------|
| `/packages/hub/src/Data/ExampleInfo.php` | Parse new front matter fields |
| `/packages/hub/src/Data/Example.php` | Merge front matter with config |

### Phase 3
| File | Change |
|------|--------|
| `/packages/hub/src/Services/ExampleRepository.php` | Add `forPackage()` filter |
| `/packages/hub/src/Commands/ListAllExamples.php` | Add package argument |
| `/packages/hub/src/Commands/RunOneExample.php` | Support `pkg:example` syntax |

## Conclusion

The recommended approach achieves the goals of modularization while avoiding the substantial risks and effort of full restructuring. Each phase can be implemented independently and stopped at any point. Most importantly, Phase 0 and Phase 1 address the core issues (duplication, hardcoded mapping) with minimal effort.
