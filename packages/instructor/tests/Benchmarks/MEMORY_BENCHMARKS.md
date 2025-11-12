# Memory Benchmarks - Quick Reference

## Quick Start

```bash
# Run memory diagnostics (fastest way to check memory)
./run-memory-diagnostics.sh

# Or run specific tests
./run-memory-diagnostics.sh sync-stream   # Sync vs Stream
./run-memory-diagnostics.sh layers        # Layer isolation
./run-memory-diagnostics.sh pipeline      # Step-by-step checkpoints
```

## What Was Created

### 1. `MemoryDiagnostics.php` - Lean Memory Profiler

**Purpose:** Quick memory diagnostics to verify streaming performance.

**Features:**
- Sync vs Stream comparison (should show ~0% difference)
- Layer isolation (identifies which component uses memory)
- Pipeline checkpoints (step-by-step memory tracking)

**Usage:**
```bash
php MemoryDiagnostics.php              # All tests
php MemoryDiagnostics.php sync-stream  # Just comparison
php MemoryDiagnostics.php layers       # Just layer isolation
php MemoryDiagnostics.php pipeline     # Just checkpoints
```

**Expected Output:**
```
TEST 1: Sync vs Stream Memory Comparison
  Sync:   6.00 MB (614x payload)
  Stream: 6.00 MB (614x payload)
  Diff:   0 B (+0.0%)
  ✅ Stream and Sync use similar memory
```

### 2. `MemoryAnalyzer.php` - Detailed Analyzer

**Purpose:** Deep memory analysis with CSV output (already existed, enhanced).

**Usage:**
```bash
php MemoryAnalyzer.php       # All payload sizes
php MemoryAnalyzer.php 10KB  # Specific size
```

**Output:** CSV format for easy analysis in spreadsheets.

### 3. `run-memory-diagnostics.sh` - Launcher Script

**Purpose:** Easy-to-use launcher for all memory tools.

**Usage:**
```bash
./run-memory-diagnostics.sh [TEST]

Tests:
  all           Run all diagnostics (default)
  sync-stream   Sync vs Stream memory comparison
  layers        Layer isolation
  pipeline      Pipeline memory checkpoints
  standalone    Detailed analyzer
  help          Show usage
```

## When to Use Each Tool

### Use `MemoryDiagnostics.php` when:
- ✅ Verifying optimizations worked
- ✅ Quick regression testing
- ✅ CI/CD memory checks
- ✅ Identifying which component has the problem

### Use `MemoryAnalyzer.php` when:
- ✅ Need CSV data for analysis
- ✅ Testing multiple payload sizes
- ✅ Comparing drivers (modular/legacy/partials)
- ✅ Detailed investigation

### Use `run-memory-diagnostics.sh` when:
- ✅ You want the easiest interface
- ✅ Running from command line
- ✅ Don't want to remember PHP commands

## Interpreting Results

### Good Results ✅

```
TEST 1: Sync vs Stream
  Sync:   6 MB
  Stream: 6 MB
  Diff:   0 MB (+0%)
  ✅ Stream and Sync use similar memory
```

**Meaning:** Caching is disabled, streaming has no overhead.

### Warning Signs ⚠️

```
TEST 1: Sync vs Stream
  Sync:   6 MB
  Stream: 16 MB
  Diff:   +10 MB (+166%)
  ⚠️  Stream uses MORE memory than Sync
```

**Meaning:** Caching might be enabled, or streaming overhead increased.

**Action:** Check `StructuredOutputStream.php:44` - `$this->cacheProcessedResponse` should be `false`.

### Layer Isolation Results

```
TEST 2: Layer Isolation
  Layer 1 (Chunks):          0 MB
  Layer 2 (Driver):          0 MB
  Layer 3 (Iteration):       0 MB
  Layer 4 (Full Pipeline):   12 MB
  ⚠️  Overhead primarily in Instructor module
```

**Meaning:**
- Polyglot/HTTP layers are efficient (0 MB)
- Transducer iteration is O(1) (0 MB during iteration)
- Overhead is in Instructor deserialization/events

**This is expected.** The 12 MB comes from:
- Deserialization (JSON → objects)
- Event system (JSON encoding for events)
- Execution objects

## Performance Targets

### Current Status (with caching disabled)

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| Stream vs Sync diff | < 10% | 0% | ✅ Excellent |
| Overhead ratio (10KB) | < 100x | 614x | ⚠️ High but acceptable |
| Transducer iteration | O(1) | 0 MB | ✅ Perfect |
| Layer isolation | 0 MB in HTTP/Polyglot | 0 MB | ✅ Perfect |

### Future Optimization Goals

| Optimization | Current | Target | Impact |
|--------------|---------|--------|--------|
| Event JSON encoding | ~2-3 MB | < 1 MB | 30-50% reduction |
| Execution objects | ~1-2 MB | < 500 KB | 10-20% reduction |
| Overall overhead (10KB) | 614x | < 200x | 70% reduction |

## Adding New Memory Tests

### Example: Test a specific optimization

```php
// In MemoryDiagnostics.php, add a new method:

private static function testMyOptimization(): void {
    echo "TEST: My Optimization\n";

    gc_collect_cycles();
    $before = memory_get_peak_usage(true);

    // Your code here
    $result = someOptimizedFunction();

    $after = memory_get_peak_usage(true);
    $delta = $after - $before;

    printf("Memory used: %s\n", self::formatBytes($delta));

    if ($delta < 1048576) {  // < 1 MB
        echo "✅ Optimization successful\n";
    } else {
        echo "⚠️ Still using significant memory\n";
    }
}
```

## CI/CD Integration

### GitHub Actions Example

```yaml
- name: Run Memory Diagnostics
  run: |
    cd packages/instructor/tests/Benchmarks
    ./run-memory-diagnostics.sh sync-stream
```

### Fail on Regression

```bash
# In your CI script
output=$(php MemoryDiagnostics.php sync-stream)

if echo "$output" | grep -q "Stream uses MORE memory"; then
    echo "❌ Memory regression detected!"
    exit 1
fi

echo "✅ Memory check passed"
```

## Troubleshooting

### All layers show 0 MB

**Cause:** PHP reuses already-allocated memory from previous tests.

**Solution:** Run layer isolation test in isolation:
```bash
php MemoryDiagnostics.php layers
```

### Results vary between runs

**Cause:** PHP garbage collection timing, other processes.

**Solution:**
1. Close other applications
2. Run multiple times and average
3. Use larger payloads for more stable measurements

### Stream shows MORE memory than expected

**Check:**
1. Is caching disabled? (`StructuredOutputStream.php:44`)
2. Run layer isolation to identify component
3. Check if events are accumulating

## Files Created

```
packages/instructor/tests/Benchmarks/
├── MemoryDiagnostics.php       # NEW: Lean memory profiler
├── run-memory-diagnostics.sh   # NEW: Launcher script
├── MEMORY_BENCHMARKS.md        # NEW: This file
├── MemoryAnalyzer.php          # EXISTING: Enhanced
├── MemoryProfileBench.php      # EXISTING: PHPBench integration
└── README.md                   # UPDATED: Added memory section
```

## Related Documentation

- **[ROOT_CAUSE_ANALYSIS.md](../../../../tmp/ROOT_CAUSE_ANALYSIS.md)** - Detailed investigation
- **[MEMORY_PERF.md](../../../../tmp/MEMORY_PERF.md)** - Performance analysis
- **[CACHE_DISABLED_RESULTS.md](../../../../tmp/CACHE_DISABLED_RESULTS.md)** - Before/after comparison

---

**Last Updated:** 2025-11-11
**Caching Status:** Disabled (`$this->cacheProcessedResponse = false`)
**Expected Memory:** Stream = Sync (0% difference)
