# Performance Benchmarks

## Streaming Driver Benchmark

The `StructuredOutputStreamingBench` compares performance of three streaming driver implementations:

### Drivers

1. **Clean** (default) - Transducer-based pipeline
   - O(1) memory overhead
   - Functional composition
   - Single source of truth for content
   - Architecture: [STREAMING.md](../../STREAMING.md)

2. **Legacy** - Original implementation
   - Higher memory usage
   - Stateful reducers
   - Dual content tracking
   - Maintained for compatibility

3. **Partials** - Intermediate implementation
   - Between Clean and Legacy in performance
   - Partial JSON parsing
   - Different accumulation strategy

### Running Benchmarks

**All benchmarks:**
```bash
composer bench
```

**All streaming driver benchmarks:**
```bash
composer bench -- --filter=StructuredOutputStreamingBench
```

**Specific driver streaming:**
```bash
composer bench -- --filter=benchCleanStream1KB
composer bench -- --filter=benchLegacyStream1KB
composer bench -- --filter=benchPartialsStream1KB
```

**Specific driver sync (baseline):**
```bash
composer bench -- --filter=benchCleanSync1KB
composer bench -- --filter=benchLegacySync1KB
composer bench -- --filter=benchPartialsSync1KB
```

**With memory profiling:**
```bash
composer bench -- --filter=StructuredOutputStreamingBench --profile
```

**Generate report:**
```bash
composer bench -- --filter=StructuredOutputStreamingBench --report=aggregate
```

**Compare specific benchmarks:**
```bash
# Compare all three streaming drivers
composer bench -- --filter="benchCleanStream1KB|benchLegacyStream1KB|benchPartialsStream1KB"
```

### Test Scenarios

| Method                  | Driver   | Mode      | Data Size | Chunks |
|-------------------------|----------|-----------|-----------|--------|
| benchCleanStream1KB     | Clean    | Streaming | 1KB       | ~30    |
| benchLegacyStream1KB    | Legacy   | Streaming | 1KB       | ~30    |
| benchPartialsStream1KB  | Partials | Streaming | 1KB       | ~30    |
| benchCleanSync1KB       | Clean    | Sync      | 1KB       | 1      |
| benchLegacySync1KB      | Legacy   | Sync      | 1KB       | 1      |
| benchPartialsSync1KB    | Partials | Sync      | 1KB       | 1      |

### Expected Results

**Time Performance (lower is better):**
- Clean: Fastest in streaming (10-30% faster than Legacy)
- Partials: Medium (between Clean and Legacy)
- Legacy: Slowest in streaming
- All similar in sync mode (< 5% difference)

**Memory Usage (lower is better):**
- Clean: Lowest (~constant overhead)
- Partials: Medium
- Legacy: Highest (more state accumulation)

### Sample Output

```
PHPBench 1.x.x

\StructuredOutputStreamingBench

    benchCleanStream1KB................I4 - Mo2.156ms (±0.52%)
    benchLegacyStream1KB...............I4 - Mo2.891ms (±0.68%)
    benchPartialsStream1KB.............I4 - Mo2.423ms (±0.45%)
    benchCleanSync1KB..................I4 - Mo1.234ms (±0.31%)
    benchLegacySync1KB.................I4 - Mo1.267ms (±0.29%)
    benchPartialsSync1KB...............I4 - Mo1.251ms (±0.33%)

6 subjects, 30 iterations, 1,200 revs, 0 rejects, 0 failures, 0 warnings
(best [mean mode] worst) = 1.234 [2.053 2.156] 2.891 (ms)
⅀T: 12.318ms μSD/r 0.008ms μRSD/r: 0.466%
```

### Interpreting Results

1. **time_avg**: Average execution time per operation
   - Compare streaming modes: Clean should be fastest
   - Compare sync modes: Should be similar across drivers

2. **mem_peak**: Peak memory usage
   - Clean should have lowest across all benchmarks
   - Legacy should have highest

3. **time_dev**: Standard deviation
   - Lower is better (more consistent)
   - Should be < 1% for reliable results

### Performance Goals

Clean driver should demonstrate:
- ✅ 10-30% faster than Legacy in streaming
- ✅ 20-40% lower memory usage
- ✅ Similar or better consistency (time_dev)
- ✅ Equivalent sync mode performance

### Configuration

Each benchmark explicitly sets the streaming driver:

```php
$so = (new StructuredOutput)
    ->withDriver($driver)
    ->withConfig((new StructuredOutputConfig())->with(streamingDriver: 'clean'))
    ->with(...);
```

This ensures accurate comparison between drivers.

## Adding More Benchmarks

To add larger payload benchmarks:

1. Create `make10KBStream()` and `make10KBJson()` methods
2. Copy existing benchmark methods
3. Rename to `bench*Stream10KB` and `bench*Sync10KB`
4. Adjust `@Revs` annotation (fewer revs for larger payloads)

Example:
```php
/**
 * @Revs(50)      // Fewer revs for larger payload
 * @Iterations(5)
 * @Warmup(2)
 */
public function benchCleanStream10KB(): void
{
    $driver = new FakeInferenceDriver(streamBatches: [ $this->make10KBStream() ]);
    // ... rest same as 1KB version
}
```

## Troubleshooting

**Benchmark fails:**
- Ensure `composer install` ran successfully
- Check PHP version >= 8.2
- Verify FakeInferenceDriver is available

**Inconsistent results (high time_dev):**
- Close other applications
- Run multiple iterations: `--iterations=10`
- Increase warmup: `@Warmup(5)`

**Memory profiling not showing:**
- Add `--profile` flag
- Check PHPBench version supports profiling
- Run with `--report=aggregate` for detailed view
