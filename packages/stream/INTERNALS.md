# Stream Package Internals

## Architecture Overview

The Stream package implements **transducers** - composable algorithmic transformations decoupled from input/output concerns. The architecture follows a strict **Source → Transform → Sink** pattern with three core abstractions:

```
┌──────────┐      ┌──────────────┐      ┌────────┐
│  Source  │──→───│ Transducers  │──→───│  Sink  │
│ (Stream) │      │(Transformation)│      │(Reducer)│
└──────────┘      └──────────────┘      └────────┘
```

### Core Contracts

#### `Cognesy\Stream\Contracts\Stream`

```php
interface Stream extends IteratorAggregate {
    public function getIterator(): Iterator;
}
```

**Purpose**: Represents a source of data that can be iterated.

**Implementations**: 24+ stream sources across Array, Filesystem, HTTP, Text, CSV, JSON categories.

#### `Cognesy\Stream\Contracts\Transducer`

```php
interface Transducer {
    public function __invoke(Reducer $reducer): Reducer;
}
```

**Purpose**: Transforms a reducer into a new reducer, enabling composition without knowing about input/output.

**Key Insight**: Transducers operate on the **reduction process itself**, not on collections directly. This enables:
- Composition before execution
- Single-pass processing
- Early termination
- Reusability across different source/sink combinations

#### `Cognesy\Stream\Contracts\Reducer`

```php
interface Reducer {
    public function init(): mixed;
    public function step(mixed $accumulator, mixed $reducible): mixed;
    public function complete(mixed $accumulator): mixed;
}
```

**Purpose**: Defines how to accumulate values into a result.

**Three-Phase Protocol**:
1. `init()` - Create initial accumulator
2. `step()` - Process each element, return new accumulator
3. `complete()` - Finalize and return result

## Component Deep Dive

### 1. Transformation

**Location**: `src/Transformation.php`

**Role**: Main API for defining and executing transductions. Implements both the command pattern (configuration) and the Transducer interface (composition).

#### State Management

```php
final readonly class Transformation implements Transducer {
    private array $transducers;    // Array<Transducer>
    private ?Reducer $sink;        // Terminal operation
    private ?iterable $source;     // Input data
}
```

**Three States**:
1. **Definition** - Has transducers, no source/sink (reusable)
2. **Configured** - Has source OR sink attached
3. **Ready** - Has all three: transducers, source, and sink

#### Immutability Pattern

All mutators return new instances:

```php
public function through(Transducer ...$transducers): self {
    return $this->with(transducers: [...$this->transducers, ...$transducers]);
}

private function with(?array $transducers = null, ?Reducer $sink = null, ?iterable $source = null): self {
    return new self(
        transducers: $transducers ?? $this->transducers,
        sink: $sink ?? $this->sink ?? new ToArrayReducer(),
        source: $source ?? $this->source,
    );
}
```

#### Composite Pattern Implementation

Transformation implements Transducer, enabling **closure** - transformations can be composed into other transformations:

```php
public function __invoke(Reducer $reducer): Reducer {
    return (new Compose($this->transducers))($reducer);
}
```

This allows:

```php
$normalize = Transformation::define(new Trim(), new ToLowercase());
$pipeline = Transformation::define($normalize, new Filter(...));
```

#### Execution Flow

```
┌─────────────────────┐
│ execute()           │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ execution()         │ Check source set, create TransformationExecution
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ makeTransduction()  │ Build reducer stack + initialize
└──────┬──────────────┘
       │
       ├─→ makeReducerStack()  → Compose transducers with sink
       │
       └─→ TransformationExecution(reducerStack, iterator, initialAcc)
```

#### Composition Semantics

**`before(Transformation)`** - Prepend logic:

```php
public function before(Transformation $transformation): self {
    return $transformation
        ->through(...$this->transducers)
        ->withSink($this->sink)        // Current sink
        ->withInput($this->source);     // Current source
}
```

```
$b->before($a)  =>  [a's transducers] + [b's transducers]
```

**`after(Transformation)`** - Append logic:

```php
public function after(Transformation $transformation): self {
    return $this
        ->with(transducers: [...$this->transducers, ...$transformation->transducers])
        ->withSink($transformation->sink)    // Other's sink
        ->withInput($transformation->source); // Other's source
}
```

```
$a->after($b)  =>  [a's transducers] + [b's transducers]
```

### 2. TransformationExecution

**Location**: `src/TransformationExecution.php`

**Role**: Manages stateful, step-by-step execution of a transduction. Separates execution concern from definition.

#### State Machine

```php
class TransformationExecution {
    private Reducer $reducerStack;
    private Iterator $iterator;
    private mixed $accumulator;
    private bool $exhausted;   // No more source elements
    private bool $completed;   // Completion phase executed
}
```

**Two-Phase Lifecycle**:

1. **Reduction Phase** (`step()` repeatedly):
   - Process one source element
   - Update accumulator
   - Check for early termination

2. **Completion Phase** (`completed()` once):
   - Finalize accumulator
   - Return result

#### Step Execution Logic

```php
public function step(): mixed {
    if ($this->exhausted) {
        throw new LogicException('No more reduction steps available.');
    }
    $this->accumulator = $this->tryProcessNext();
    return $this->accumulator;
}

private function tryProcessNext(): mixed {
    $newAccumulator = $this->reducerStack->step(
        $this->accumulator,
        $this->iterator->current()
    );

    return match(true) {
        $newAccumulator instanceof Reduced => $this->onReduced($newAccumulator),
        default => $this->onNext($newAccumulator),
    };
}
```

#### Early Termination Mechanism

When a transducer wants to stop processing (e.g., `TakeN` reaches limit), it wraps the accumulator in `Reduced`:

```php
return new Reduced($accumulator);
```

`TransformationExecution` detects this and sets `$exhausted = true`, preventing further iteration.

**Why Separate `exhausted` and `completed`?**

- `exhausted` = No more `step()` calls possible
- `completed` = Completion phase has been run

This separation allows:
- Progressive rendering (iterate steps without completion)
- Deferred finalization (complete() runs once, after all steps)

### 3. TransformationStream

**Location**: `src/TransformationStream.php`

**Role**: Provides lazy, iterator-based stream processing by wrapping Transformation with `ToQueueReducer`.

#### Lazy Evaluation Strategy

```php
final class TransformationStream implements Stream {
    private readonly iterable $input;
    private readonly Transformation $transformation;
    private ?TransformationExecution $execution;
    private ?SplQueue $queue;
    private ?Iterator $iterator;
}
```

**Initialization is deferred** until `getIterator()` is called:

```php
public function getIterator(): Iterator {
    if ($this->iterator === null) {
        $this->iterator = $this->makeIterator($this->execution());
    }
    return $this->iterator;
}
```

#### Two-Phase Iteration

```php
private function iterate(TransformationExecution $transduction, SplQueue $queue): Iterator {
    while ($transduction->hasNextStep()) {
        // Phase 1: Execute one transduction step
        $transduction->step();

        // Phase 2: Yield all queued items from this step
        while (!$queue->isEmpty()) {
            yield $queue->dequeue();
        }
    }
}
```

**Why the queue?**

Some transducers emit multiple values per input (e.g., `FlatMap`, `Chunk`). The queue buffers these emissions, allowing the stream to yield them individually rather than as collections.

**Execution Flow**:

```
Input: [1, 2, 3]
Transducer: FlatMap(fn($x) => [$x, $x*10])

Step 1: process 1 → queue [1, 10] → yield 1 → yield 10
Step 2: process 2 → queue [2, 20] → yield 2 → yield 20
Step 3: process 3 → queue [3, 30] → yield 3 → yield 30
```

### 4. Transducer Composition

**Location**: `src/Transducers/Compose.php`

**Role**: Compose multiple transducers into a single transducer.

#### Right-to-Left Composition

```php
final readonly class Compose implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return array_reduce(
            array: array_reverse($this->transducers),
            callback: fn(Reducer $acc, Transducer $t) => $t($acc),
            initial: $reducer,
        );
    }
}
```

**Why reverse?**

Function composition works right-to-left to match mathematical composition:

```
f(g(h(x)))  =  (f ∘ g ∘ h)(x)
```

Given transducers `[t1, t2, t3]` and sink `s`:

```php
Compose([t1, t2, t3])(s)
  = t1(t2(t3(s)))
```

**Execution order is left-to-right** because data flows through the composed reducers:

```
Data → t1 → t2 → t3 → sink
```

### 5. Reduced (Early Termination)

**Location**: `src/Support/Reduced.php`

**Role**: Signal early termination from within a transducer's step function.

```php
final readonly class Reduced {
    public function __construct(private mixed $value) {}
    public function value(): mixed { return $this->value; }
}
```

**Usage Pattern**:

```php
// In a Transducer's step():
if ($shouldTerminate) {
    return new Reduced($accumulator);
}
return $this->next->step($accumulator, $value);
```

**Detection** happens in `TransformationExecution::tryProcessNext()`:

```php
return match(true) {
    $newAccumulator instanceof Reduced => $this->onReduced($newAccumulator),
    default => $this->onNext($newAccumulator),
};
```

### 6. Tee (Parallel Processing)

**Location**: `src/Support/Tee.php`

**Role**: Split a single-pass iterator into N independent iterators that can be consumed at different rates.

#### Architecture

```
                    ┌─────────────┐
    Source ─────→───│  TeeState   │
                    │   (shared)  │
                    └──────┬──────┘
                           │
            ┌──────────────┼──────────────┐
            │              │              │
        Branch 0       Branch 1       Branch 2
        Iterator       Iterator       Iterator
```

#### TeeState: Shared Buffer Manager

**Location**: `src/Support/TeeState.php`

**Key Data Structures**:

```php
private array $buffer = [];         // [logicalIndex => value]
private int $head = 0;               // Oldest buffered position
private int $tail = 0;               // Next position to write
private array $cursor = [];          // [branchId => currentPosition]
private array $active = [];          // [branchId => bool]
```

**Buffer Lifecycle**:

```
Initial:    head=0, tail=0, buffer=[]
After read: head=0, tail=1, buffer=[0 => 'value1']
After read: head=0, tail=2, buffer=[0 => 'value1', 1 => 'value2']

Branch 0 advances to position 1:
  cursor[0] = 1

If all branches at position >= 1:
  Evict buffer[0]
  head = 1
  buffer = [1 => 'value2']
```

#### Consumption Patterns

**Pattern 1: Synchronized Consumption**

```php
[$a, $b] = Tee::split($data, 2);

// Both advance at same rate
foreach ($a as $val) { /* ... */ }
foreach ($b as $val) { /* ... */ }

// Buffer stays minimal (≤1 value)
```

**Pattern 2: Fast/Slow Consumer**

```php
[$fast, $slow] = Tee::split($data, 2);

// Fast consumer gets ahead
foreach ($fast as $i => $val) {
    if ($i > 100) break;  // Stop early
}

// Buffer grows up to 100 items
foreach ($slow as $val) {
    // Reads from buffer, then source
}
```

**Pattern 3: Abandoned Branch**

```php
[$a, $b] = Tee::split($data, 2);

// Branch A abandoned early
foreach ($a as $i => $val) {
    if ($i > 5) break;
}
// TeeState::deactivate(0) called via finally block

// Buffer now tracks only Branch B
foreach ($b as $val) {
    // Memory usage based on B only
}
```

#### Memory Management

**Eviction Strategy**:

```php
private function cleanupBuffer(): void {
    $slowestPosition = $this->findSlowestActivePosition();

    if ($slowestPosition === null) {
        $this->clearBuffer();  // All branches inactive
        return;
    }

    if ($slowestPosition <= $this->head) {
        return;  // Nothing to evict
    }

    $this->evictValuesBeforePosition($slowestPosition);
}
```

**Eviction is triggered after every value retrieval**, keeping memory usage proportional to the spread between fastest and slowest active branches.

### 7. Iterator Support

#### TransformationIterator

**Location**: `src/Support/TransformationIterator.php`

**Role**: Single-pass iterator over transduction steps (no rewind).

**Key Behavior**:

```php
public function rewind(): void {
    if ($this->started) {
        throw new LogicException('Cannot rewind single-pass transduction iterator');
    }
    $this->started = true;
    $this->loadNextValue();
}
```

**Use Case**: Progressive rendering where you iterate once and display intermediate accumulator states.

#### BufferedTransformationIterator

**Location**: `src/Support/BufferedTransformationIterator.php`

**Role**: Rewindable iterator that buffers all intermediate results.

**Memory Trade-off**: O(n) space to enable multiple iterations.

**Implementation**:

```php
private function ensurePositionLoaded(): void {
    if (isset($this->buffer[$this->position])) {
        return;  // Already buffered
    }

    if ($this->fullyConsumed) {
        return;  // No more values
    }

    // Fill buffer up to current position
    while (!isset($this->buffer[$this->position])) {
        if (!$this->execution->hasNextStep()) {
            $this->fullyConsumed = true;
            return;
        }

        $nextIndex = count($this->buffer);
        $this->buffer[$nextIndex] = $this->execution->step();
    }
}
```

**Lazy buffering**: Values are computed and stored only when accessed.

## Design Patterns

### 1. Transducer Pattern

**Core Idea**: Separate algorithmic transformation from data structure concerns.

```php
interface Transducer {
    public function __invoke(Reducer $reducer): Reducer;
}
```

**Benefits**:
- **Reusability**: Same transducer works with arrays, streams, files, HTTP
- **Composition**: Transducers compose into transducers (closure property)
- **Efficiency**: Single-pass processing, early termination
- **Testability**: Test transformations without I/O

### 2. Decorator Pattern (Reducer Wrapping)

Each transducer decorates a reducer with additional behavior:

```php
class FilterTransducer implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer, $this->predicate) implements Reducer {
            public function step(mixed $acc, mixed $val): mixed {
                if ($this->predicate($val)) {
                    return $this->next->step($acc, $val);  // Pass through
                }
                return $acc;  // Skip
            }
            // ...
        };
    }
}
```

### 3. Builder Pattern (Transformation)

Transformation uses fluent interface with immutability:

```php
Transformation::define(new Map(...))
    ->through(new Filter(...))
    ->withInput($data)
    ->withSink(new ToArrayReducer())
    ->execute();
```

### 4. Iterator Pattern (Streams & Tee)

All streams implement `IteratorAggregate`, enabling `foreach` usage:

```php
interface Stream extends IteratorAggregate {
    public function getIterator(): Iterator;
}
```

### 5. Strategy Pattern (Reducers)

Different accumulation strategies via Reducer interface:

- `ToArrayReducer` - Collect to array
- `SumReducer` - Calculate sum
- `FindReducer` - Search + early terminate
- `GroupByReducer` - Partition data

### 6. Lazy Evaluation

TransformationStream defers all computation until iteration:

```php
$stream = TransformationStream::from($input)->through(...$ops);
// Nothing executed yet

foreach ($stream as $value) {
    // Computation happens here, on-demand
}
```

### 7. Composite Pattern

Transformation implements Transducer, enabling hierarchical composition:

```php
$base = Transformation::define(new Map(...));
$extended = Transformation::define($base, new Filter(...));
```

## Extending the Package

### Creating Custom Transducers

**Template**:

```php
use Cognesy\Stream\Contracts\{Transducer, Reducer};

final readonly class MyTransducer implements Transducer {
    public function __construct(/* parameters */) {}

    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer, /* captured params */) implements Reducer {
            public function __construct(
                private Reducer $next,
                // private params
            ) {}

            public function init(): mixed {
                return $this->next->init();
            }

            public function step(mixed $accumulator, mixed $value): mixed {
                // Transform value
                $transformed = /* your logic */;

                // Optionally early terminate
                if ($shouldStop) {
                    return new Reduced($accumulator);
                }

                // Pass to next reducer
                return $this->next->step($accumulator, $transformed);
            }

            public function complete(mixed $accumulator): mixed {
                // Optional finalization
                return $this->next->complete($accumulator);
            }
        };
    }
}
```

**Key Points**:

1. Capture dependencies in constructor
2. Return anonymous Reducer that wraps `$next`
3. Forward `init()` and `complete()` to `$next` (usually)
4. Transform in `step()` and forward to `$next->step()`
5. Use `Reduced` for early termination

### Creating Custom Reducers (Sinks)

**Template**:

```php
use Cognesy\Stream\Contracts\Reducer;

final class MyReducer implements Reducer {
    public function __construct(/* configuration */) {}

    public function init(): mixed {
        // Return initial accumulator
        return [];  // or 0, null, new SomeObject(), etc.
    }

    public function step(mixed $accumulator, mixed $value): mixed {
        // Accumulate value
        $accumulator[] = process($value);
        return $accumulator;

        // Or early terminate:
        if ($done) {
            return new Reduced($accumulator);
        }
    }

    public function complete(mixed $accumulator): mixed {
        // Finalize result (optional transformation)
        return array_values($accumulator);
    }
}
```

**Patterns**:

- **Collecting**: Accumulate into collection (array, queue)
- **Aggregating**: Reduce to single value (sum, count, min/max)
- **Searching**: Find + early terminate
- **Side Effects**: Execute actions, return void or count

### Creating Custom Streams

**Template**:

```php
use Cognesy\Stream\Contracts\Stream;

final readonly class MyStream implements Stream {
    public function __construct(/* source params */) {}

    public static function from(/* params */): self {
        return new self(/* params */);
    }

    public function getIterator(): \Iterator {
        // Return iterator over your data source
        foreach ($this->generateData() as $item) {
            yield $item;
        }
    }

    private function generateData(): iterable {
        // Your data generation logic
    }
}
```

**Examples**:

- Database query result stream
- WebSocket message stream
- Sensor data stream
- File watcher stream

## Performance Characteristics

### Time Complexity

| Operation | Complexity | Notes |
|-----------|------------|-------|
| Transducer composition | O(n) where n = # transducers | One-time cost |
| Step execution | O(1) per step | Amortized |
| Early termination | O(k) where k = steps until termination | vs O(n) full scan |
| Tee split (synchronized) | O(1) per value | Minimal buffer |
| Tee split (divergent) | O(d) per value where d = divergence | Buffer size = fastest - slowest |

### Space Complexity

| Component | Memory Usage | Notes |
|-----------|-------------|-------|
| Transformation (definition) | O(t) where t = # transducers | Just references |
| TransformationExecution | O(1) | Single accumulator |
| TransformationStream | O(q) where q = queue size | Bounded by emission rate |
| BufferedTransformationIterator | O(n) where n = # steps | Stores all intermediates |
| Tee split | O(d×b) where d=divergence, b=branches | Grows with spread |

### When to Use What

**Transformation** (Eager):
- Small datasets that fit in memory
- When you need the final result immediately
- Multiple operations on same data

**TransformationStream** (Lazy):
- Large datasets (files, HTTP streams)
- When partial results are useful
- Memory-constrained environments

**Tee Split**:
- Same data needs multiple different transformations
- Parallel processing paths
- Real-time + archival use cases

**Buffered Iterator**:
- Need to iterate multiple times
- Debugging/analysis of intermediate states
- Acceptable memory overhead

## Testing Strategies

### Testing Transducers

```php
test('MyTransducer transforms correctly', function() {
    $input = [1, 2, 3];
    $result = Transformation::define(new MyTransducer())
        ->withInput($input)
        ->execute();

    expect($result)->toBe([2, 4, 6]);
});
```

### Testing Reducers

```php
test('MyReducer accumulates correctly', function() {
    $reducer = new MyReducer();
    $acc = $reducer->init();

    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);

    $result = $reducer->complete($acc);

    expect($result)->toBe(6);
});
```

### Testing Composition

```php
test('Composed transformations work correctly', function() {
    $t1 = Transformation::define(new Map(fn($x) => $x * 2));
    $t2 = Transformation::define(new Filter(fn($x) => $x > 5));

    $composed = Transformation::define($t1, $t2);

    $result = $composed->executeOn([1, 2, 3, 4, 5]);

    expect($result)->toBe([6, 8, 10]);
});
```

## Debugging Tips

### 1. Use Tap for Inspection

```php
Transformation::define(
    new Map(fn($x) => $x * 2),
    new Tap(fn($x) => dump($x)),  // Inspect here
    new Filter(fn($x) => $x > 5)
);
```

### 2. Progressive Rendering for Step Analysis

```php
$iterator = $transformation->iterator();
foreach ($iterator as $step => $accumulator) {
    echo "Step $step: ";
    var_dump($accumulator);
}
```

### 3. Buffered Iterator for Rewind Debugging

```php
$iterator = $transformation->iterator(buffered: true);

// First pass
foreach ($iterator as $result) { /* inspect */ }

// Second pass - compare behavior
foreach ($iterator as $result) { /* compare */ }
```

### 4. Simplify Composition

Test transducers individually before composing:

```php
// Test each separately
$t1Result = Transformation::define($t1)->executeOn($input);
$t2Result = Transformation::define($t2)->executeOn($t1Result);

// Then test composed
$composed = Transformation::define($t1, $t2)->executeOn($input);
```

## Common Pitfalls

### 1. Mutating Accumulators

❌ **Wrong**:
```php
public function step(mixed $acc, mixed $val): mixed {
    $acc[] = $val;  // Mutates
    return $acc;
}
```

✅ **Correct**:
```php
public function step(mixed $acc, mixed $val): mixed {
    return [...$acc, $val];  // Immutable
}
```

### 2. Forgetting to Forward init/complete

❌ **Wrong**:
```php
public function init(): mixed {
    return [];  // Don't hardcode!
}
```

✅ **Correct**:
```php
public function init(): mixed {
    return $this->next->init();  // Forward to next
}
```

### 3. Unwrapping Reduced Too Early

❌ **Wrong**:
```php
$result = $this->next->step($acc, $val);
return $result->value();  // Crash if not Reduced
```

✅ **Correct**:
```php
$result = $this->next->step($acc, $val);
if ($result instanceof Reduced) {
    return $result;  // Propagate upward
}
return $result;
```

### 4. Tee Branch Abandonment Without Cleanup

❌ **Wrong**:
```php
[$a, $b] = Tee::split($data, 2);
foreach ($a as $val) { break; }  // Abandoned
// TeeState still tracking branch A
```

✅ **Correct**:
```php
// Tee::makeBranch uses try/finally to call deactivate()
// Ensure you don't suppress exceptions or break out of scope improperly
```

### 5. Reusing TransformationExecution

❌ **Wrong**:
```php
$execution = $transformation->execution();
$result1 = $execution->completed();
$result2 = $execution->completed();  // Throws LogicException
```

✅ **Correct**:
```php
$result1 = $transformation->execute();
$result2 = $transformation->execute();  // Creates new execution each time
```

## Future Extensions

Potential areas for extension:

1. **Async Transducers** - Support for async operations in transducers
2. **Parallel Execution** - Multi-threaded transduction
3. **Backpressure** - Rate limiting for fast producers
4. **Metrics/Observability** - Built-in performance tracking
5. **More Stream Sources** - Database, message queues, sockets
6. **Stateful Transducers** - Shared state across branches
7. **Conditional Tee** - Route to branches based on predicates
