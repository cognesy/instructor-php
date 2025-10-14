# Stream Package Cheatsheet

## Quick Reference

### Core Concepts

```php
// Three ways to process data
1. Transformation::define() -> define reusable transformations
2. TransformationStream::from() -> lazy stream processing
3. Tee::split() -> parallel path processing
```

## Transformation API

### Define & Execute

```php
use Cognesy\Stream\Transform\Filter\Transducers\Filter;use Cognesy\Stream\Transform\Limit\Transducers\TakeN;use Cognesy\Stream\Transform\Map\Transducers\{Map};use Cognesy\Stream\Transformation;

// Define reusable transformation
$pipeline = Transformation::define(
    new Map(fn($x) => $x * 2),
    new Filter(fn($x) => $x > 5),
    new TakeN(3)
);

// Execute on data
$result = $pipeline->withInput([1,2,3,4,5])->execute();
// => [6, 8, 10]

// Or use executeOn() shorthand
$result = $pipeline->executeOn([1,2,3,4,5]);
```

### Custom Sinks

```php
use Cognesy\Stream\Sinks\{GroupByReducer,Stats\SumReducer,ToStringReducer};

// Collect to string
$text = Transformation::define(new Map('strtoupper'))
    ->withInput(['foo', 'bar'])
    ->withSink(new ToStringReducer(separator: ', '))
    ->execute();
// => "FOO, BAR"

// Calculate sum
$total = Transformation::define(new Map(fn($x) => $x * 2))
    ->withInput([1, 2, 3])
    ->withSink(new SumReducer())
    ->execute();
// => 12

// Group by key
$groups = Transformation::define()
    ->withInput([['type'=>'a','val'=>1], ['type'=>'b','val'=>2], ['type'=>'a','val'=>3]])
    ->withSink(new GroupByReducer(fn($x) => $x['type']))
    ->execute();
// => ['a' => [...], 'b' => [...]]
```

### Composition

```php
// Compose transformations as building blocks
$normalize = Transformation::define(
    new Map(fn($x) => trim($x)),
    new Filter(fn($x) => strlen($x) > 0)
);

$enrich = Transformation::define(
    new Map(fn($x) => strtoupper($x)),
    new Map(fn($x) => "[$x]")
);

// Method 1: through()
$pipeline = Transformation::define($normalize, $enrich);

// Method 2: before() - semantic ordering
$pipeline = $enrich->before($normalize);
// Executes: normalize → enrich

// Method 3: after() - semantic ordering
$pipeline = $normalize->after($enrich);
// Executes: normalize → enrich
```

### Progressive Rendering

```php
// Get iterator for step-by-step processing
$iterator = Transformation::define(new Map(fn($x) => $x * 2))
    ->withInput([1, 2, 3])
    ->withSink(new ToArrayReducer())
    ->iterator();

foreach ($iterator as $step => $intermediateResult) {
    // $step 0: [2]
    // $step 1: [2, 4]
    // $step 2: [2, 4, 6]
    echo "Progress: " . ($step + 1) . "\n";
}

// Buffered iterator (rewindable)
$buffered = $pipeline->iterator(buffered: true);
foreach ($buffered as $result) { /* ... */ }
foreach ($buffered as $result) { /* ... */ } // Works!
```

## TransformationStream API

### Lazy Processing

```php
use Cognesy\Stream\TransformationStream;
use Cognesy\Stream\Sources\Array\ArrayStream;

// Create lazy stream
$stream = TransformationStream::from([1, 2, 3, 4, 5])
    ->through(
        new Map(fn($x) => $x * 2),
        new Filter(fn($x) => $x > 5)
    );

// Nothing executed yet - lazy!
foreach ($stream as $value) {
    echo $value; // 6, 8, 10
}

// Get completed result
$result = $stream->getCompleted(); // [6, 8, 10]
```

### Using Predefined Transformations

```php
$normalize = Transformation::define(
    new Map(fn($x) => trim($x)),
    new Filter(fn($x) => strlen($x) > 0)
);

$stream = TransformationStream::from(['  foo  ', '', '  bar  '])
    ->using($normalize);
```

## Stream Sources

### File Processing

```php
use Cognesy\Stream\Sources\Filesystem\{FileLineStream, FileStream, DirectoryStream};

// Read file line by line
FileLineStream::from('data.txt')->through(
    new Filter(fn($line) => !str_starts_with($line, '#')),
    new Map(fn($line) => json_decode($line, true))
);

// Read file in chunks
FileStream::chunk('large.bin', chunkSize: 8192);

// List directory files
DirectoryStream::files('/path/to/dir')
    ->through(new Filter(fn($file) => str_ends_with($file, '.php')));
```

### HTTP Streaming

```php
use Cognesy\Stream\Sources\Http\{HttpLineStream, HttpEventStream};

// Stream HTTP response lines
HttpLineStream::from('https://api.example.com/data')
    ->through(
        new Map(fn($line) => json_decode($line, true)),
        new Filter(fn($data) => $data['active'] ?? false)
    );

// Server-Sent Events
HttpEventStream::from('https://api.example.com/events')
    ->through(new Map(fn($event) => $event['data']));
```

### CSV & JSON

```php
use Cognesy\Stream\Sources\Csv\CsvStream;
use Cognesy\Stream\Sources\Json\JsonlStream;

// CSV with headers
CsvStream::withHeaders('data.csv')
    ->through(new Filter(fn($row) => $row['active'] === 'true'));

// JSONL (newline-delimited JSON)
JsonlStream::decoded('data.jsonl')
    ->through(new Map(fn($obj) => $obj['name']));
```

### Text Processing

```php
use Cognesy\Stream\Sources\Text\{TextStream, TextCharStream, TextWordStream};

// Character stream
TextStream::chars("Hello World")
    ->through(new Filter(fn($c) => ctype_alpha($c)));

// Word stream
TextStream::words("The quick brown fox")
    ->through(new Map('strtoupper'));
```

## Transducers (37+)

### Filtering

```php
new Filter(fn($x) => $x > 5)           // Keep matching
new Remove(fn($x) => $x < 0)           // Remove matching
new Keep(fn($x) => $x['id'] ?? null)   // Keep non-null results
new TakeN(10)                          // Take first N
new TakeWhile(fn($x) => $x < 100)      // Take while predicate true
new TakeUntil(fn($x) => $x === null)   // Take until predicate true
new TakeLast(5)                        // Take last N
new TakeNth(2)                         // Take every Nth (0, 2, 4...)
new DropFirst(3)                       // Skip first N
new DropLast(2)                        // Drop last N
new DropWhile(fn($x) => $x < 5)        // Drop while predicate true
new DropUntil(fn($x) => $x > 10)       // Drop until predicate true
```

### Mapping

```php
new Map(fn($x) => $x * 2)              // Transform each element
new MapIndexed(fn($x, $i) => [$i, $x]) // Map with index
new FlatMap(fn($x) => [$x, $x*2])      // Map + flatten
new Scan(fn($acc, $x) => $acc + $x, 0) // Accumulate with intermediates
new Replace([1 => 'one', 2 => 'two'])  // Replace values via map
```

### Deduplication

```php
new Distinct()                         // Remove duplicates
new DistinctBy(fn($x) => $x['id'])     // Dedupe by key
new Deduplicate()                      // Alias for Distinct
```

### Chunking & Windowing

```php
new Chunk(3)                           // [[1,2,3], [4,5,6], ...]
new Pairwise()                         // [[1,2], [2,3], [3,4], ...]
new SlidingWindow(3)                   // [[1,2,3], [2,3,4], ...]
new PartitionBy(fn($x) => $x % 2)      // Group by predicate change
```

### Combining

```php
new Zip($iter1, $iter2)                // [[a1,b1], [a2,b2], ...]
new ZipWith(fn($a,$b) => $a+$b, $i1)   // Zip with combiner function
new Interleave($iter1, $iter2)         // [a1,b1,a2,b2, ...]
```

### Adding/Removing

```php
new Append([7, 8, 9])                  // Add items to end
new Prepend([0, 1])                    // Add items to start
new Interpose(',')                     // Insert between elements
```

### Flattening

```php
new Flatten()                          // [[1,2], [3,4]] => [1,2,3,4]
new Cat()                              // Concatenate nested iterables
```

### Repetition

```php
new Cycle(3)                           // [1,2] => [1,2,1,2,1,2]
new Repeat(5)                          // Repeat each element N times
```

### Utilities

```php
new Tap(fn($x) => log($x))             // Side effects without changing stream
new TryCatch(fn($x) => risky($x), fn($e) => null) // Error handling
new RandomSample(0.1)                  // Sample 10% randomly
```

## Sinks (17)

### Collection

```php
new ToArrayReducer()                   // Default: [1, 2, 3]
new ToStringReducer(', ')              // "1, 2, 3"
new ToQueueReducer($queue)             // SplQueue
```

### Aggregation

```php
new SumReducer()                       // Sum all values
new CountReducer()                     // Count elements
new AverageReducer()                   // Calculate average
new MinReducer()                       // Find minimum
new MaxReducer()                       // Find maximum
```

### Grouping

```php
new GroupByReducer(fn($x) => $x['category'])
// => ['cat1' => [...], 'cat2' => [...]]

new FrequenciesReducer()
// [1,1,2,3,3,3] => [1=>2, 2=>1, 3=>3]
```

### Search

```php
new FindReducer(fn($x) => $x > 10, default: null)
new FirstReducer(default: null)
new LastReducer(default: null)
```

### Predicates

```php
new MatchesAllReducer(fn($x) => $x > 0)  // All match?
new MatchesAnyReducer(fn($x) => $x < 0)  // Any match?
new MatchesNoneReducer(fn($x) => $x === null) // None match?
```

### Side Effects

```php
new ForEachReducer(fn($x) => echo $x . "\n")
```

## Parallel Processing with Tee

### Basic Split

```php
use Cognesy\Stream\Support\Tee;

[$iter1, $iter2, $iter3] = Tee::split($source, branches: 3);

// Process same data differently
foreach ($iter1 as $val) { /* path 1 */ }
foreach ($iter2 as $val) { /* path 2 */ }
foreach ($iter3 as $val) { /* path 3 */ }
```

### Different Transformations

```php
$data = [1, 2, 3, 4, 5];
[$branch1, $branch2] = Tee::split($data, 2);

// Branch 1: Double values
$doubled = Transformation::define(new Map(fn($x) => $x * 2))
    ->executeOn($branch1);

// Branch 2: Filter evens
$evens = Transformation::define(new Filter(fn($x) => $x % 2 === 0))
    ->executeOn($branch2);
```

### Progressive Rendering

```php
$logs = FileLineStream::from('app.log');
[$display, $archive] = Tee::split($logs, 2);

// Real-time display
$displayTask = async(function() use ($display) {
    foreach ($display as $line) {
        echo $line;
        flush();
    }
});

// Archive to file
$archiveTask = async(function() use ($archive) {
    $fp = fopen('archive.log', 'w');
    foreach ($archive as $line) {
        fwrite($fp, $line);
    }
    fclose($fp);
});
```

### Memory Management

```php
// Fast consumer doesn't wait for slow consumer
[$fast, $slow] = Tee::split($hugeDataset, 2);

foreach ($fast as $i => $item) {
    process_quickly($item);
    if ($i > 10) break; // Stop early
}
// TeeState automatically cleans up buffer for stopped branches

foreach ($slow as $item) {
    expensive_processing($item);
}
```

## Common Patterns

### ETL Pipeline

```php
$etl = Transformation::define(
    // Extract
    new Map(fn($row) => explode(',', $row)),
    // Transform
    new Map(fn($cols) => [
        'id' => (int)$cols[0],
        'name' => trim($cols[1]),
        'email' => strtolower($cols[2])
    ]),
    new Filter(fn($row) => filter_var($row['email'], FILTER_VALIDATE_EMAIL)),
    // Load
    new Tap(fn($row) => $db->insert('users', $row))
);

$etl->executeOn(FileLineStream::from('users.csv'));
```

### Data Validation

```php
$validator = Transformation::define(
    new Filter(fn($x) => is_array($x)),
    new Filter(fn($x) => isset($x['id'], $x['name'])),
    new Map(fn($x) => [
        'id' => (int)$x['id'],
        'name' => trim($x['name']),
        'valid' => true
    ])
);

$valid = $validator->executeOn($untrustedData);
```

### Batch Processing

```php
$batchProcessor = Transformation::define(
    new Chunk(100),
    new Tap(fn($batch) => $api->bulkInsert($batch)),
    new FlatMap(fn($batch) => $batch) // Flatten back
);

$processed = $batchProcessor->executeOn($records);
```

### Real-time Analytics

```php
$analytics = Transformation::define(
    new Filter(fn($event) => $event['type'] === 'purchase'),
    new Map(fn($event) => $event['amount']),
    new Scan(fn($total, $amount) => $total + $amount, 0)
);

$runningTotal = $analytics->iterator()
    ->withInput(HttpEventStream::from('https://api/events'));

foreach ($runningTotal as $step => $total) {
    echo "Total revenue: $total\n";
}
```

### Data Deduplication

```php
$dedupe = Transformation::define(
    new Map(fn($x) => strtolower(trim($x))),
    new Filter(fn($x) => strlen($x) > 0),
    new Distinct()
);

$unique = $dedupe->executeOn(['Foo', 'foo ', ' bar', '', 'Foo', 'Bar']);
// => ['foo', 'bar']
```

## Performance Tips

1. **Use TransformationStream for large datasets** - Lazy evaluation
2. **Prefer Transformation for small datasets** - Direct execution
3. **Tee::split() for parallel paths** - Process same data multiple ways
4. **Buffered iterators only when needed** - Memory overhead for rewind support
5. **Early termination with TakeN/TakeWhile** - Stop processing ASAP
6. **Chunk large operations** - Better memory usage with batching

## Error Handling

```php
use Cognesy\Stream\Transform\Misc\Transducers\TryCatch;

$safe = Transformation::define(
    new Map(fn($x) => json_decode($x, true)),
    new TryCatch(
        try: fn($data) => $data['value'],
        catch: fn($e) => null
    ),
    new Filter(fn($x) => $x !== null)
);

$results = $safe->executeOn($jsonStrings);
```

## Extending

### Custom Transducer

```php
use Cognesy\Stream\Contracts\{Transducer, Reducer};

class MyTransducer implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer) implements Reducer {
            public function __construct(private Reducer $next) {}

            public function init(): mixed {
                return $this->inner->init();
            }

            public function step(mixed $acc, mixed $val): mixed {
                // Your transformation logic
                return $this->inner->step($acc, $transformedValue);
            }

            public function complete(mixed $acc): mixed {
                return $this->inner->complete($acc);
            }
        };
    }
}
```

### Custom Sink

```php
class MyReducer implements Reducer {
    public function init(): mixed {
        return /* initial accumulator */;
    }

    public function step(mixed $acc, mixed $val): mixed {
        // Accumulate values
        return $acc;
    }

    public function complete(mixed $acc): mixed {
        // Finalize and return result
        return $acc;
    }
}
```
