# Stream Package Cheatsheet

Code-verified reference for `packages/stream`.

## Core API

```php
use Cognesy\Stream\Transformation;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transform\Limit\Transducers\TakeN;
use Cognesy\Stream\Transform\Map\Transducers\Map;

$pipeline = Transformation::define(
    new Map(fn($x) => $x * 2),
    new Filter(fn($x) => $x > 5),
    new TakeN(3),
);

$result = $pipeline->executeOn([1, 2, 3, 4, 5, 6]);
// [6, 8, 10]
```

## `Transformation` Methods

```php
use Cognesy\Stream\Sinks\ToStringReducer;

$pipeline = Transformation::define();

$pipeline = $pipeline
    ->through($transducerA, $transducerB)
    ->withSink(new ToStringReducer(separator: ', '))
    ->withInput($iterable);

$result = $pipeline->execute();
$result = $pipeline->executeOn($iterable);

$execution = $pipeline->execution();
$iterator = $pipeline->iterator();
$bufferedIterator = $pipeline->iterator(buffered: true);

$other = Transformation::define($transducerC);
$combined1 = $pipeline->before($other);
$combined2 = $pipeline->after($other);
```

## `TransformationStream` (Lazy Stream)

```php
use Cognesy\Stream\TransformationStream;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transform\Map\Transducers\Map;

$stream = TransformationStream::from([1, 2, 3, 4])
    ->through(new Map(fn($x) => $x * 2))
    ->through(new Filter(fn($x) => $x > 4));

foreach ($stream as $value) {
    // 6, 8
}

$all = $stream->getCompleted(); // [6, 8]
```

`TransformationStream` is emission-oriented:
- `getCompleted()` returns the values emitted by the stream
- completed streams retain emitted values in memory so they can be replayed
- custom sinks on a reused `Transformation` are ignored
- use `Transformation::execute()` / `executeOn()` when you need sink-specific completion results

Use predefined transformation:

```php
$normalize = Transformation::define(
    new Map(fn($x) => trim((string) $x)),
    new Filter(fn($x) => $x !== ''),
);

$stream = TransformationStream::from(['  foo  ', '', ' bar '])
    ->using($normalize);
```

## `Tee` (Split One Stream Into Many)

```php
use Cognesy\Stream\Support\Tee;

[$left, $right] = Tee::split([1, 2, 3, 4], branches: 2);

$leftItems = iterator_to_array($left, false);
$rightItems = iterator_to_array($right, false);
```

Unused branches still count as active until they are released. Drop never-consumed branches promptly if another branch may run far ahead.

## Source Facades

### Array

```php
use Cognesy\Stream\Sources\Array\ArrayStream;

$stream = ArrayStream::from([1, 2, 3]);
```

### Filesystem

```php
use Cognesy\Stream\Sources\Filesystem\DirectoryStream;
use Cognesy\Stream\Sources\Filesystem\FileStream;

$file = FileStream::fromPath('data.txt');
$lines = $file->lines(dropEmpty: true);
$chunks = $file->chunks(4096);
$bytes = $file->bytes();

$dir = DirectoryStream::from('/tmp')->withExtensions('php', 'md');
$files = $dir->files();
$dirs = $dir->dirs();
$any = $dir->any();
```

### CSV / JSONL

```php
use Cognesy\Stream\Sources\Csv\CsvStream;
use Cognesy\Stream\Sources\Json\JsonlStream;

$csvRows = CsvStream::fromPath('data.csv')->rows();
$csvAssocRows = CsvStream::fromPath('data.csv')->rowsAssoc();

$jsonlLines = JsonlStream::fromPath('data.jsonl')->lines();
$jsonlDecoded = JsonlStream::fromPath('data.jsonl')->decoded();
```

### Text

```php
use Cognesy\Stream\Sources\Text\TextStream;

$text = TextStream::from("Hello world\nNext line");
$chars = $text->chars();
$lines = $text->lines();
$words = $text->words();
```

### HTTP Chunks

```php
use Cognesy\Stream\Sources\Http\HttpStream;
use Generator;

$chunks = (function (): Generator {
    yield "data: one\n\n";
    yield "data: two\n\n";
})();

$http = HttpStream::from($chunks);
$bytes = $http->bytes();
$lines = $http->lines();
$events = $http->events();
```

## Common Transducers

```php
use Cognesy\Stream\Transform\Deduplicate\Transducers\Distinct;
use Cognesy\Stream\Transform\Deduplicate\Transducers\DistinctBy;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transform\Flatten\Transducers\FlatMap;
use Cognesy\Stream\Transform\Group\Transducers\Chunk;
use Cognesy\Stream\Transform\Group\Transducers\Pairwise;
use Cognesy\Stream\Transform\Group\Transducers\SlidingWindow;
use Cognesy\Stream\Transform\Limit\Transducers\DropFirst;
use Cognesy\Stream\Transform\Limit\Transducers\TakeN;
use Cognesy\Stream\Transform\Limit\Transducers\TakeWhile;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transform\Misc\Transducers\Tap;
use Cognesy\Stream\Transform\Misc\Transducers\TryCatch;
use Throwable;

new Map(fn($x) => $x);
new Filter(fn($x) => true);
new TakeN(10);
new DropFirst(2);
new TakeWhile(fn($x) => $x !== null);
new FlatMap(fn($x) => [$x, $x]);
new Distinct();
new DistinctBy(fn($x) => $x['id']);
new Chunk(100);
new Pairwise();
new SlidingWindow(3);
new Tap(fn($x) => null);
new TryCatch(
    tryFn: fn($x) => $x,
    onError: fn(Throwable $e, mixed $value) => null,
    throwOnError: false,
);
```

## Common Sinks (Reducers)

```php
use Cognesy\Stream\Sinks\Bool\MatchesAnyReducer;
use Cognesy\Stream\Sinks\GroupByReducer;
use Cognesy\Stream\Sinks\Select\FindReducer;
use Cognesy\Stream\Sinks\Stats\AverageReducer;
use Cognesy\Stream\Sinks\Stats\CountReducer;
use Cognesy\Stream\Sinks\Stats\SumReducer;
use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Sinks\ToStringReducer;

new ToArrayReducer();
new ToStringReducer(separator: ', ');
new SumReducer();
new AverageReducer();
new CountReducer();
new GroupByReducer(fn($x) => $x['type']);
new FindReducer(fn($x) => $x > 10, default: null);
new MatchesAnyReducer(fn($x) => $x < 0);
```

## Custom Transducer / Reducer

```php
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

final readonly class MyTransducer implements Transducer
{
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer) implements Reducer {
            public function __construct(private Reducer $next) {}

            public function init(): mixed {
                return $this->next->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed {
                $transformed = $reducible;
                return $this->next->step($accumulator, $transformed);
            }

            public function complete(mixed $accumulator): mixed {
                return $this->next->complete($accumulator);
            }
        };
    }
}
```
