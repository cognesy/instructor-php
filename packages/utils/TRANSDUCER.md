# Transducer Library

A composable, memory-efficient stream processing library for PHP that enables lazy, functional data transformations with early termination support.

## Core Concepts

### What Are Transducers?

Transducers are composable algorithmic transformations that are decoupled from the input and output sources. They provide:

- **Composability**: Chain transformations into complex pipelines
- **Reusability**: Apply the same transformations to different data structures
- **Performance**: Single-pass processing without intermediate collections
- **Early Termination**: Stop processing when conditions are met via `Reduced` wrapper

### Core Interfaces

#### Reducer

The fundamental building block that defines how to aggregate values:

```php
interface Reducer {
    public function init(): mixed;              // Initialize accumulator
    public function step(mixed $accumulator, mixed $reducible): mixed;  // Process one item
    public function complete(mixed $accumulator): mixed;                // Finalize result
}
```

#### Transducer

A function that transforms one reducer into another:

```php
interface Transducer {
    public function __invoke(Reducer $reducer): Reducer;
}
```

#### Reduced

A wrapper class signaling early termination:

```php
final readonly class Reduced {
    public function __construct(private mixed $value) {}
    public function value(): mixed { return $this->value; }
}
```

When a reducer's `step()` method returns `Reduced`, the transduction process stops immediately and the wrapped value is extracted via `complete()`.

## Basic Usage

### Simple Pipeline

```php
use Cognesy\Stream\Sinks\ToArrayReducer;use Cognesy\Stream\Transducers\{Filter};use Cognesy\Stream\Transducers\Map;use Cognesy\Stream\Transducers\TakeN;use Cognesy\Stream\Transformation;

$result = (new Transformation(
    transducers: [
        new Filter(fn($x) => $x > 0),
        new Map(fn($x) => $x * 2),
        new TakeN(5),
    ],
    reducer: new ToArrayReducer(),
))->executeOn([1, -2, 3, 4, 5, 6, 7]);

// Result: [2, 6, 8, 10, 12]
```

### Fluent API

```php
$pipeline = new Transduce([], new ToArrayReducer());

$result = $pipeline
    ->through(new Filter(fn($x) => $x !== null))
    ->through(new Map(fn($x) => strtoupper($x)))
    ->through(new TakeN(10))
    ->run($data);
```

### Composition

Manually compose transducers for complex transformations:

```php
use Cognesy\Stream\Transducers\Compose;

$composed = Compose::from(
    new Filter(fn($x) => $x > 0),
    new Map(fn($x) => $x * 2),
    new TakeN(100)
);

$result = (new Transduce([$composed], new SumReducer()))->run($input);
```

## Early Termination

Transducers support efficient early termination via the `Reduced` wrapper. When a reducer returns `Reduced`, processing stops immediately.

### Built-in Early Termination

These transducers/reducers support early termination:

- `TakeN` - Stops after N elements
- `TakeWhile` - Stops when predicate fails
- `TakeUntil` - Stops when predicate succeeds (inclusive)
- `FindReducer` - Stops when element is found

### Example: Finding First Match

```php
use Cognesy\Stream\Sinks\FindReducer;

$result = (new Transduce(
    transducers: [
        new Filter(fn($x) => $x > 0),
    ],
    reducer: new FindReducer(fn($x) => $x > 100),
))->run(range(1, 10000));

// Stops immediately after finding first value > 100
// Result: 101
```

### Custom Early Termination

```php
use Cognesy\Stream\Support\Reduced;

class LimitSumReducer implements Reducer {
    public function __construct(private int $maxSum) {}

    public function init(): mixed { return 0; }

    public function step(mixed $accumulator, mixed $reducible): mixed {
        $newSum = $accumulator + $reducible;
        if ($newSum >= $this->maxSum) {
            return new Reduced($newSum);  // Stop early
        }
        return $newSum;
    }

    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}

// Processing stops as soon as sum reaches or exceeds 100
$result = (new Transduce([], new LimitSumReducer(100)))->run($data);
```

## Reducer Categories

### Decorators

Decorators wrap another reducer and modify its behavior. They maintain internal state and delegate to the wrapped reducer.

**Key Characteristics:**
- Accept a `Reducer` in constructor
- Transform data before passing to wrapped reducer
- May buffer elements (e.g., `ChunkReducer`, `PartitionByReducer`)
- Support early termination (e.g., `TakeNReducer`)

**Common Decorators:**
- `TakeNReducer` - Takes first N elements, returns `Reduced` after limit
- `ChunkReducer` - Buffers elements into fixed-size arrays, flushes in `complete()`
- `PartitionByReducer` - Groups consecutive elements by key, flushes partition on key change
- `DistinctReducer` - Tracks seen values to eliminate duplicates

### Sinks

Sinks are terminal reducers that aggregate values into a final result. They don't wrap other reducers.

**Key Characteristics:**
- Implement `Reducer` directly
- Produce final output format
- Examples: arrays, sums, counts, grouped data

**Common Sinks:**

```php
// Collection Sinks
new ToArrayReducer()                                    // Collect to array
new ToStringReducer($separator)                         // Join strings
new GroupByReducer(fn($x) => $x->category)             // Group by key
new FrequenciesReducer()                               // Count occurrences

// Aggregation Sinks
new SumReducer()                                        // Sum values
new CountReducer()                                      // Count items
new MaxReducer()                                        // Find maximum
new MinReducer()                                        // Find minimum
new AverageReducer()                                    // Calculate average

// Search Sinks
new FindReducer(fn($x) => $x > 10)                     // Find first match (early termination)
new FirstReducer()                                      // Get first element (early termination)
new LastReducer()                                       // Get last element
```

### CallableReducer

A flexible reducer that accepts closures:

```php
use Cognesy\Stream\Support\CallableReducer;

$customReducer = new CallableReducer(
    stepFn: fn($acc, $item) => $acc + $item,
    completeFn: fn($acc) => $acc / 100,
    initFn: fn() => 0
);

$result = (new Transduce([], $customReducer))->run($numbers);
```

## Transducer Catalog (37 Total)

### Basic Transformation (5)

- **Map** - Transform each element: `new Map(fn($x) => $x * 2)`
- **MapIndexed** - Transform with index: `new MapIndexed(fn($val, $idx) => "$idx: $val")`
- **Replace** - Replace values: `new Replace(['old' => 'new'])`
- **Tap** - Side effects: `new Tap(fn($x) => error_log($x))`
- **TryCatch** - Safe transformation: `new TryCatch(fn($x) => parse($x), onError: fn($e) => null)`

### Filtering & Selection (3)

- **Filter** - Keep matching: `new Filter(fn($x) => $x > 0)`
- **Remove** - Exclude matching: `new Remove(fn($x) => $x === null)`
- **Keep** - Map and filter: `new Keep(fn($str) => filter_var($str, FILTER_VALIDATE_EMAIL) ?: null)`

### Taking & Dropping (9)

**Take:**
- **TakeN** - First N elements: `new TakeN(10)` *(early termination)*
- **TakeWhile** - While true: `new TakeWhile(fn($x) => $x < 100)` *(early termination)*
- **TakeUntil** - Until true (inclusive): `new TakeUntil(fn($x) => $x === 'END')` *(early termination)*
- **TakeLast** - Last N elements: `new TakeLast(5)` *(requires buffering)*
- **TakeNth** - Every Nth: `new TakeNth(3)`

**Drop:**
- **DropN** - Skip first N: `new DropN(5)` *(alias: DropFirst)*
- **DropWhile** - Skip while true: `new DropWhile(fn($x) => $x < 0)`
- **DropUntil** - Skip until true (inclusive): `new DropUntil(fn($x) => $x === 'START')`
- **DropLast** - Remove last N: `new DropLast(2)` *(requires buffering)*

### Deduplication (3)

- **Deduplicate** - Remove consecutive duplicates: `new Deduplicate()`
- **Distinct** - Remove all duplicates: `new Distinct()` *(global state)*
- **DistinctBy** - Unique by key: `new DistinctBy(fn($user) => $user->email)` *(global state)*

### Flattening & Expansion (3)

- **Cat** - Flatten one level: `new Cat()` - `[[1,2], [3,4]] => [1,2,3,4]`
- **FlatMap** - Map then flatten: `new FlatMap(fn($user) => $user->orders)`
- **Flatten** - Deep flatten: `new Flatten(depth: 2)`

### Windowing & Batching (4)

- **Chunk** - Fixed-size batches: `new Chunk(100)` *(alias: PartitionAll)*
- **PartitionBy** - Group by key change: `new PartitionBy(fn($item) => $item->category)`
- **SlidingWindow** - Overlapping windows: `new SlidingWindow(3)` - `[1,2,3,4] => [[1,2,3], [2,3,4]]`
- **Pairwise** - Consecutive pairs: `new Pairwise()` - `[1,2,3,4] => [[1,2], [2,3], [3,4]]`

### Combining Streams (6)

- **Zip** - Combine into tuples: `new Zip([10, 20, 30])` - `[1,2,3] + [10,20,30] => [[1,10], [2,20], [3,30]]`
- **ZipWith** - Combine with function: `new ZipWith(fn($a, $b) => $a + $b, [10,20,30])`
- **Interleave** - Alternate elements: `new Interleave([10,20,30])` - `[1,2,3] + [10,20,30] => [1,10,2,20,3,30]`
- **Interpose** - Insert separator: `new Interpose(', ')` - `['a','b','c'] => ['a',', ','b',', ','c']`
- **Append** - Add at end: `new Append('END', 'DONE')`
- **Prepend** - Add at start: `new Prepend('START', 'BEGIN')`

### Repetition & Sampling (3)

- **Repeat** - Repeat each element: `new Repeat(3)` - `[1,2] => [1,1,1,2,2,2]`
- **Cycle** - Repeat entire sequence: `new Cycle(2)` - `[1,2,3] => [1,2,3,1,2,3]`
- **RandomSample** - Probabilistic sampling: `new RandomSample(0.1)` - 10% random selection

### Stateful Accumulation (1)

- **Scan** - Running accumulation: `new Scan(fn($sum, $x) => $sum + $x, 0)` - `[1,2,3,4] => [1,3,6,10]`

## Composition Patterns

### Transducer Composition

Transducers compose **right-to-left** when built, but data flows **left-to-right**:

```php
// Define pipeline (read top-to-bottom for data flow)
$pipeline = new Transduce(
    transducers: [
        new Filter(fn($x) => $x > 0),     // 1. Filter first
        new Map(fn($x) => $x * 2),        // 2. Then map
        new TakeN(5),                     // 3. Then take
    ],
    reducer: new ToArrayReducer()
);
```

The `Compose` transducer internally reverses and applies transducers to build the reducer chain correctly.

### Complex Pipeline Example

```php
$result = (new Transduce(
    transducers: [
        // 1. Clean data
        new Filter(fn($x) => $x !== null),
        new Distinct(),

        // 2. Transform
        new Map(fn($x) => normalize($x)),
        new FlatMap(fn($item) => $item->children),

        // 3. Limit
        new TakeN(1000),

        // 4. Batch for processing
        new Chunk(100),
    ],
    reducer: new ToArrayReducer()
))->run($rawData);
```

### Reusable Transformations

```php
// Define reusable transformation
$cleanAndNormalize = Compose::from(
    new Filter(fn($x) => $x !== null),
    new Map(fn($x) => trim($x)),
    new Distinct()
);

// Apply to different targets
$emails = (new Transduce([$cleanAndNormalize], new ToArrayReducer()))
    ->run($emailList);

$count = (new Transduce([$cleanAndNormalize], new CountReducer()))
    ->run($emailList);
```

## Performance Characteristics

### Memory Efficiency

Most transducers process data in **constant memory** (O(1)):
- Map, Filter, Take, Drop family - no buffering
- Single-pass processing without intermediate collections

### Buffering Transducers

These require O(N) memory:
- **TakeLast(N)** - buffers last N elements
- **DropLast(N)** - buffers N elements
- **Chunk(N)** - buffers up to N elements
- **SlidingWindow(N)** - buffers N elements
- **PartitionBy** - buffers current partition
- **Distinct/DistinctBy** - tracks all seen values

### Early Termination Benefits

Operations like `TakeN` and `FindReducer` can significantly improve performance by stopping early:

```php
// Without early termination - processes all 1M records
$result = array_slice(
    array_map(fn($x) => expensive($x), $millionRecords),
    0,
    10
);

// With early termination - processes only ~10 records
$result = (new Transduce(
    transducers: [
        new Map(fn($x) => expensive($x)),
        new TakeN(10),  // Stops after 10
    ],
    reducer: new ToArrayReducer()
))->run($millionRecords);
```

## Advanced Usage

### Custom Transducers

Create custom transducers by implementing the `Transducer` interface:

```php
use Cognesy\Stream\Contracts\{Transducer};use Cognesy\Stream\Contracts\Reducer;use Cognesy\Stream\Support\CallableReducer;

final readonly class MultiplyBy implements Transducer {
    public function __construct(private int $factor) {}

    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: fn($acc, $item) => $reducer->step($acc, $item * $this->factor),
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
```

### Custom Stateful Reducer

```php
final class RunningAverageReducer implements Reducer {
    private int $count = 0;
    private float $sum = 0;

    public function init(): mixed { return []; }

    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->count++;
        $this->sum += $reducible;
        $accumulator[] = $this->sum / $this->count;
        return $accumulator;
    }

    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}

// Usage
$runningAvgs = (new Transduce([], new RunningAverageReducer()))
    ->run([10, 20, 30, 40]);
// Result: [10, 15, 20, 25]
```

### Conditional Processing

```php
$result = (new Transduce(
    transducers: [
        new Filter(fn($x) => $x['status'] === 'active'),
        new TryCatch(
            fn($x) => processItem($x),
            onError: fn($e) => ['error' => $e->getMessage()]
        ),
        new Keep(fn($x) => !isset($x['error']) ? $x : null),
    ],
    reducer: new ToArrayReducer()
))->run($items);
```

## Best Practices

### 1. Order Matters

Place filters early to reduce processing:

```php
// Good - filter first
new Transduce([
    new Filter(fn($x) => $x > 0),      // Reduce data set
    new Map(fn($x) => expensive($x)),  // Process less
], new ToArrayReducer())

// Bad - expensive operation on all data
new Transduce([
    new Map(fn($x) => expensive($x)),  // Process all
    new Filter(fn($x) => $x > 0),      // Filter after
], new ToArrayReducer())
```

### 2. Use Early Termination

When you only need part of the result:

```php
// Instead of processing all and taking first
$first = array_values(array_filter($data, $predicate))[0] ?? null;

// Use FindReducer for early termination
$first = (new Transduce([], new FindReducer($predicate)))->run($data);
```

### 3. Prefer Composition

Build reusable transformations:

```php
$cleanData = Compose::from(
    new Filter(fn($x) => $x !== null),
    new Map(fn($x) => trim($x)),
    new Distinct()
);

// Reuse in different contexts
$emails = (new Transduce([$cleanData], new GroupByReducer($keyFn)))->run($data);
$count = (new Transduce([$cleanData], new CountReducer()))->run($data);
```

### 4. Batch Processing

Use chunking for bulk operations:

```php
(new Transduce(
    transducers: [
        new Filter(fn($x) => $x !== null),
        new Chunk(100),  // Process in batches
        new Tap(fn($batch) => $db->insertMany($batch)),
    ],
    reducer: new CountReducer()
))->run($largeDataset);
```

## Summary

**Transducers provide:**
- ✅ Composable, reusable transformations
- ✅ Single-pass, memory-efficient processing
- ✅ Early termination for performance
- ✅ Type-safe, functional pipelines
- ✅ 37 built-in transformations
- ✅ Extensible via custom transducers/reducers

**Key Components:**
- **Transducers** - Transform data (Map, Filter, Take, etc.)
- **Decorators** - Stateful reducers that wrap other reducers
- **Sinks** - Terminal reducers that produce final results
- **Reduced** - Enables early termination
- **Compose** - Combines multiple transducers

Use transducers when you need efficient, composable data processing pipelines with fine control over memory usage and execution flow.
