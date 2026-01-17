# Critical Analysis: Modular Docs Proposal

## Executive Summary

The modular docs proposal has architectural merit but introduces substantial complexity and risk that may not be justified by the stated benefits.

## 1. Complexity Added

### Already Existing Duplication
There are examples duplicated in TWO locations:
- `/examples/` (primary, 222 examples)
- `/packages/hub/examples/` (copy, 222 examples)

The proposal would create a THIRD location, compounding the problem.

### Multi-Location Discovery
Current (simple):
```php
public function __construct(string $baseDir) {
    $this->baseDir = $baseDir ?: BasePath::get('examples');
}
```

Proposed (complex):
```php
private function getDefaultDirectories(): array {
    return [
        $basePath . 'examples',                     // Legacy
        $basePath . 'packages/instructor/examples', // Package 1
        $basePath . 'packages/polyglot/examples',   // Package 2
        // ... more packages
    ];
}
```

**Problems:**
- Order-dependent discovery
- Performance hit scanning multiple directories
- Harder debugging ("where did this come from?")
- Index stability issues

### Front Matter Schema Explosion

Current (3 fields):
```yaml
title: 'Basic use'
docname: 'basic_use'
path: ''
```

Proposed (10+ fields):
```yaml
title: 'Basic use'
docname: 'basic_use'
package: 'instructor'
section: 'basics'
weight: 100
tags: ['beginner', 'core']
difficulty: 'beginner'
requires: []
requires_env: []
description: ''
```

**Problems:**
- 222 examples need manual updates
- Higher cognitive load for contributors
- More validation requirements

## 2. What Could Break

### Index Stability
`composer hub run 42` relies on stable ordering. Moving examples changes indices:
- Cached/bookmarked references break
- CI scripts using numeric indices break

### Hardcoded Mapping
The mapping in `Example.php` (17 entries) would need to work differently based on source location. Partial migration creates hybrid state.

### Documentation Generation
`MkDocsDocumentation.php` (719 lines) has assumptions about paths and structure. Dual-source examples could generate conflicting paths.

## 3. Abstraction Leaks

### Package Detection
```php
if (preg_match('#packages/([^/]+)/#', $baseDir, $matches)) {
    return $matches[1];
}
return 'core'; // fallback - what IS core?
```

Assumes directory structure never changes. Symlinks, mounted volumes would break this.

### Tab-to-Package Relationship
```php
return match($package) {
    'instructor' => 'instructor',
    'polyglot' => 'polyglot',
    default => 'cookbook'  // doesn't match any tab
};
```

Still hardcoded. Adding packages requires code changes.

### Front Matter vs Directory Conflict
What if front matter says `tab: polyglot` but file is in `packages/instructor/examples/`? No precedence rules defined.

## 4. Edge Cases Not Covered

| Edge Case | Problem |
|-----------|---------|
| Cross-package examples | Where should `CustomHttpClient` live? Uses both instructor AND http-client |
| Prompting examples | `C*` examples mapped to `prompting` tab but no `packages/prompting` exists |
| Shared resources | Examples with multiple files break on move |
| Git history | 222 file moves lose blame/history |

## 5. Is This Over-Engineering?

### What We're Actually Solving

The "problem" is a **17-line hardcoded mapping**:
```php
$mapping = [
    'A01_Basics' => ['tab' => 'instructor', 'name' => 'basics', 'title' => '...'],
    // 16 more lines
];
```

### What The Proposal Creates

| Current System | Proposed System |
|---------------|-----------------|
| 1 example location | 4+ locations |
| 17-line mapping | 222 front matter files |
| Direct file paths | Detection heuristics |
| Simple debugging | Multi-source tracing |

### Cost-Benefit

- **Benefit**: "Cleaner" package ownership (but single team maintains everything)
- **Cost**: ~75 hours, ongoing complexity, migration risk

## 6. Simpler Alternatives

### Alternative A: Externalize Mapping to Config
```yaml
# /config/examples.yaml
groups:
  A01_Basics:
    tab: instructor
    group: basics
    title: 'Basics'
```

**Effort**: ~2 hours | **Risk**: Zero

### Alternative B: Symlinks
```bash
packages/instructor/examples -> ../../examples/A*
packages/polyglot/examples -> ../../examples/B*
```

**Effort**: ~30 minutes | **Risk**: Minimal

### Alternative C: Virtual Package Mapping
```php
$repository->forPackage('instructor')->getAllExamples();
```

Keep files where they are, add package concept as a view.

**Effort**: ~4 hours | **Risk**: Low

### Alternative D: Remove Duplication First
Delete `/packages/hub/examples/` before adding any complexity.

**Effort**: ~1 hour | **Risk**: Low

## 7. Recommendations

### If Proceeding Anyway

1. **Delete duplication first** - Remove `/packages/hub/examples/`
2. **Validation command** - `composer hub validate` to catch front matter errors
3. **Migration lockfile** - Track what's migrated
4. **Feature flags** - Allow rollback without code changes
5. **Automated tests** - Verify example count, no orphans, no duplicate paths

### Recommended Approach

**Do not proceed with full modularization.**

Instead:
1. **Immediate**: Remove `/packages/hub/examples/` duplication
2. **Short-term**: Externalize mapping to config file
3. **If still needed**: Virtual package mapping
4. **Only if proven necessary**: Partial physical migration

## Conclusion

The proposal solves a problem that doesn't significantly impact the project while introducing substantial migration risk and ongoing maintenance burden. The current 17-line mapping works. Externalizing it to a config file achieves the goal of "configuration over code" with minimal effort.
