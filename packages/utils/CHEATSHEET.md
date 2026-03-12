---
title: Utils
description: Core utilities — JSON processing, collections, text manipulation, and helper functions
package: utils
---

# Utils Package Cheatsheet

Dense reference for Cognesy/Utils package capabilities. All examples show public API methods only.

## Core Utilities

### Json - JSON Processing
```php
// Creation
Json::fromString('{"key":"value"}') // Extract complete JSON from text
Json::fromPartial('{"key":"val')     // Handle incomplete JSON
Json::fromArray(['key' => 'value'])  // Convert array to Json
Json::none()                         // Empty JSON

// Conversion
$json->toArray()                     // → array (JSON root must be object or array)
$json->toString()                    // → string
$json->isEmpty()                     // → bool
$json->format(JSON_PRETTY_PRINT)    // → formatted string
$json->format(0, 64)                // → formatted string with depth limit

// Static helpers
Json::decode('{"a":1}', [])          // → mixed, with default fallback
Json::encode(['a' => 1])             // → string
Json::encode(['a' => 1], JSON_PRETTY_PRINT) // → string with options
```

Notes:
- `Json::encode()` and `Json::fromArray()` throw `InvalidArgumentException` on encoding failure.
- `Json::decode()` throws `JsonException` when no default is provided and input is invalid; returns default otherwise.

### Result - Monadic Error Handling
```php
// Creation
Result::success($value)              // → Success
Result::failure($error)              // → Failure
Result::from($anyValue)              // Auto-wrap (Throwable → failure, Result → passthrough)
Result::try(fn() => riskyOperation()) // Catch exceptions → Result

// Checking state
$result->isSuccess()                 // → bool
$result->isFailure()                 // → bool
$result->isSuccessAndNull()          // → bool (success with null value)
$result->isSuccessAndTrue()          // → bool (success with true value)
$result->isSuccessAndFalse()         // → bool (success with false value)
$result->isType('string')           // → bool (success + gettype matches)
$result->isInstanceOf(Foo::class)   // → bool (success + instanceof)
$result->matches(fn($v) => $v > 0)  // → bool (success + predicate)

// Value access
$result->unwrap()                    // → value (throws on Failure)
$result->error()                     // → error value (throws on Success)
$result->exception()                 // → Throwable (throws on Success)
$result->valueOr('default')         // Get value or fallback
$result->exceptionOr(null)          // Get exception or fallback

// Success-side transformations
$result->map(fn($v) => transform($v)) // Transform success value
$result->then(fn($v) => Result::success(transform($v))) // Chain Results
$result->ensure(fn($v) => $v > 0, fn($v) => 'too small') // Guard predicate → Failure
$result->tap(fn($v) => log($v))     // Side effect, returns self (or Failure on throw)

// Failure-side transformations
$result->recover(fn($e) => fallback($e)) // Recover from failure → Success
$result->mapError(fn($e) => new MyException($e)) // Transform error value

// Side effects
$result->ifSuccess(fn($v) => log($v)) // Execute on success, returns self
$result->ifFailure(fn($e) => log($e)) // Execute on failure, returns self

// Batch operations
Result::tryAll($args, $callback1, $callback2) // Try all, collect results or errors
Result::tryUntil($condition, $args, $callbacks) // Until condition met
```

Notes:
- `Failure::errorMessage()` converts any error to a string representation.

### Option - Optional Value Type
```php
// Creation
Option::some($value)                 // Wrap value in Some
Option::none()                       // Create None
Option::fromNullable($valueOrNull)   // null → None, otherwise Some
Option::fromResult($result)          // Success → Some, Failure → None

// Checking state
$opt->isSome()                       // → bool
$opt->isNone()                       // → bool
$opt->exists(fn($v) => $v > 0)      // Some + predicate holds → true
$opt->forAll(fn($v) => $v > 0)      // None or predicate holds → true

// Transformations
$opt->map(fn($v) => $v * 2)         // Transform inner value
$opt->flatMap(fn($v) => Option::some($v)) // Chain returning Option
$opt->andThen(fn($v) => ...)        // Alias for flatMap
$opt->filter(fn($v) => $v > 0)      // Some + predicate → Some, else None
$opt->zipWith($other, fn($a,$b) => $a+$b) // Combine two Options

// Side effects
$opt->ifSome(fn($v) => log($v))     // Execute on Some, returns self
$opt->ifNone(fn() => log('empty'))   // Execute on None, returns self

// Destructuring
$opt->match(fn() => 'none', fn($v) => $v) // Pattern match
$opt->getOrElse('default')          // Value or fallback (value or callable)
$opt->orElse(Option::some(42))      // This Option or alternative Option
$opt->toNullable()                   // → value|null

// Conversion to Result
$opt->toResult(new RuntimeException('missing')) // None → failure
$opt->toSuccessOr('fallback')       // None → success with default
```

### Str - String Operations
```php
// Splitting
Str::split('hello world', ' ')      // → ['hello', 'world']

// Case conversion
Str::pascal('hello_world')           // → HelloWorld
Str::snake('HelloWorld')             // → hello_world
Str::camel('hello-world')            // → helloWorld
Str::kebab('HelloWorld')             // → hello-world
Str::title('hello world')            // → Hello World

// Searching
Str::contains('text', 'needle', true) // Case sensitive search (default)
Str::containsAll('text', ['a','b'])   // All needles present
Str::containsAny('text', ['a','b'])   // Any needle present
Str::startsWith('text', 'prefix')     // → bool
Str::endsWith('text', 'suffix')       // → bool

// Extraction
Str::between('a<content>b', '<', '>') // → content
Str::after('prefix:content', ':')     // → content
Str::limit('long text', 5, '...')     // → long...
Str::limit('long text', 5, '...', STR_PAD_LEFT) // → ...text (trim from left)
Str::limit('long text', 5, '...', STR_PAD_RIGHT, false) // → long ... (no fit)

// Conditionals
Str::when(true, 'yes', 'no')         // → yes
```

### Arrays - Array Utilities
```php
// Transformation
Arrays::asArray($value)              // Force to array (null → [], scalar → [$value])
Arrays::map($array, fn($v,$k) => $v) // Map with key access
Arrays::flatten([$nested, $arrays])  // → flat array
Arrays::fromAny($object)             // Convert any type to array (handles circular refs)

// Merging
Arrays::mergeNull($arr1, $arr2)      // Merge handling nulls → ?array
Arrays::mergeMany($iterableOfArrays) // Efficiently merge many arrays
Arrays::mergeOver($items, fn($item, $key) => [...]) // Map to arrays then merge

// Manipulation
Arrays::unset($array, ['key1','key2']) // Remove keys
Arrays::removeTail($array, 2)        // Remove last N elements
Arrays::removeRecursively($arr, $keys, $skip) // Deep key removal

// Validation
Arrays::isSubset($subset, $full)     // → bool
Arrays::valuesMatch($arr1, $arr2)    // Same values, any order
Arrays::hasOnlyStrings($array)       // → bool

// Output
Arrays::toBullets($array)            // → "- item1\n- item2"
Arrays::flattenToString($arrays, ' ') // Join nested arrays
```

### Files - File System Operations
```php
// Directory operations
Files::removeDirectory($path)        // Recursive delete → bool
Files::copyDirectory($src, $dst)     // Recursive copy (throws on error, rejects symlinks)
Files::renameFileExtensions($dir, 'md', 'mdx') // Batch rename

// File operations
Files::copyFile($src, $dst)          // Copy with dir creation (throws)

// Iteration
Files::files($path)                  // → Iterator<SplFileInfo> (files only)
Files::directories($path)            // → Iterator<SplFileInfo> (dirs only)
```

## Data Structures

### DataMap - Nested Data Access
```php
// Creation
new DataMap(['key' => 'value'])
DataMap::fromArray($array)
DataMap::fromJson('{"key":"value"}') // JSON root must be object or array

// Dot notation access
$map->get('user.name', 'default')    // Deep get with fallback
$map->set('user.name', 'John')       // Deep set
$map->has('user.email')              // → bool
$map->getType('user.age')            // → 'integer'

// Magic access
$map->user                           // → DataMap|mixed
$map->user = 'value'                 // Set value
isset($map->user)                    // Check existence

// Conversion
$map->toArray()                      // → array
$map->toJson(JSON_PRETTY_PRINT)     // → string
$map->fields()                       // → array of top-level keys

// Manipulation
$map->merge(['new' => 'data'])       // Add/update data
$map->except('key1', 'key2')         // New map without keys
$map->only('key1', 'key2')           // New map with only keys
$map->with(['key' => 'value'])       // New map with added data

// Advanced operations
$map->toMap('users.*')               // Aimeos\Map with wildcard collection
$map->clone()                        // Deep clone
```

### ImmutableDataMap - Read-Only DataMap
```php
new ImmutableDataMap(['key' => 'value'])

$map->get('user.name', 'default')    // Deep get with fallback
$map->has('user.email')              // → bool
$map->toArray()                      // → array
$map->toJson(JSON_PRETTY_PRINT)     // → string
$map->jsonSerialize()                // → mixed (for json_encode)
```

### CachedMap - Lazy-Loading Map
```php
// Creation
new CachedMap(fn($key) => compute($key), $preloaded)
CachedMap::from(fn($key) => compute($key), $preloaded)

// Access
$map->get('key', ...$extraArgs)      // Resolve once, cache result
$map->set('key', $value)             // Manually set, bypassing producer
$map->has('key')                     // → bool (exists in cache)
$map->isResolved('key')              // → bool (computed or manually set)

// Cache management
$map->forget('key')                  // Clear cache for key
$map->fresh()                        // Clear all cached values
$map->keys()                         // → array of cached keys
$map->toArray()                      // → array of all cached values
$map->count()                        // → int (resolved entries)

// Array access (implements ArrayAccess, IteratorAggregate, Countable)
$map['key']                          // same as get('key')
$map['key'] = $value                 // same as set('key', $value)
isset($map['key'])                   // same as isResolved('key')
unset($map['key'])                   // same as forget('key')
foreach ($map as $k => $v) { }      // iterate cached entries
```

### Context - Typed Service Container
```php
// Creation
Context::empty()
new Context(['Service' => $instance])

// Service management (class-string keys)
$ctx->with(Service::class, $service) // → new Context (immutable)
$ctx->get(Service::class)            // → T (throws MissingServiceException)
$ctx->tryGet(Service::class)         // → Result<T, MissingServiceException>
$ctx->has(Service::class)            // → bool
$ctx->merge($otherContext)           // → new Context (right-bias)

// Keyed service management (Key<T> tokens)
$key = Key::of('myLogger', LoggerInterface::class) // → Key<T>
$ctx->withKey($key, $logger)         // → new Context (immutable)
$ctx->getKey($key)                   // → T (throws MissingServiceException)
```

### Key - Typed Service Token
```php
Key::of('id', ServiceClass::class)   // → Key<T>
$key->id                             // → string (unique identifier)
$key->type                           // → class-string<T>
```

### Layer - Context Composition
```php
// Simple providers
Layer::provides(Service::class, $svc)           // Static service
Layer::providesFrom(Service::class, fn($ctx) => new Svc()) // Factory
Layer::providesKey($key, $svc)                  // Keyed service
Layer::providesFromKey($key, fn($ctx) => new Svc()) // Keyed factory

// Composition
$layer->dependsOn($other)           // → Layer (other builds first, then this)
$layer->referredBy($other)          // → Layer (this builds first, then other)
$layer->merge($other)               // → Layer (parallel merge, right-bias)

// Apply
$layer->applyTo($context)           // → Context
```

### Container - DI Container
```php
// SimpleContainer — standalone container
$c = new SimpleContainer();

// PsrContainer — wraps any PSR-11, overlays writes
$c = new PsrContainer($psrContainer);

// Registration
$c->set('id', fn(Container $c) => new Svc())       // Transient (new each time)
$c->singleton('id', fn(Container $c) => new Svc())  // Singleton (cached)
$c->instance('id', $object)                          // Pre-built instance

// Resolution (PSR-11 compatible)
$c->get('id')                        // → mixed (throws NotFoundException)
$c->has('id')                        // → bool
```

### ArrayList - Immutable Indexed List
```php
// Creation
ArrayList::empty()
ArrayList::of($a, $b, $c)
ArrayList::fromArray([$a, $b, $c])

// Access
$list->count()                       // → int
$list->isEmpty()                     // → bool
$list->itemAt(0)                     // → T (throws OutOfBoundsException)
$list->getOrNull(0)                  // → T|null
$list->first()                       // → T|null
$list->last()                        // → T|null

// Immutable operations (return new list)
$list->withAppended($item1, $item2)  // Append items
$list->withInserted(1, $item)        // Insert at index
$list->withRemovedAt(0, 2)           // Remove N items at index
$list->filter(fn($v) => $v > 0)     // Filter by predicate
$list->map(fn($v) => $v * 2)        // Transform items
$list->reduce(fn($acc, $v) => $acc + $v, 0) // Fold
$list->concat($otherList)           // Concatenate lists
$list->reverse()                     // Reverse order

// Conversion
$list->all()                         // → list<T>
$list->toArray()                     // → list<T>
```

### ArrayMap - Immutable Key-Value Map
```php
// Creation
ArrayMap::empty()
ArrayMap::fromArray(['a' => 1, 'b' => 2])

// Access
$map->count()                        // → int
$map->has('key')                     // → bool
$map->get('key')                     // → V (throws OutOfBoundsException)
$map->getOrNull('key')              // → V|null
$map->keys()                         // → list<K>
$map->values()                       // → list<V>

// Immutable operations (return new map)
$map->with('key', $value)           // Add/replace entry
$map->withAll(['a' => 1, 'b' => 2]) // Add entries (existing keys preserved)
$map->withRemoved('key')            // Remove entry (idempotent)
$map->merge($otherMap)              // Merge (other wins on collisions)

// Conversion
$map->toArray()                      // → array<K,V>
```

### ArraySet - Immutable Hash-Based Set
```php
// Creation (requires hash function)
ArraySet::empty(fn($item) => $item->id())
ArraySet::fromValues(fn($item) => $item->id(), $values)
ArraySet::fromValues(fn($i) => $i->id(), $values, fn($a,$b) => $a->equals($b))

// Access
$set->count()                        // → int
$set->contains($item)               // → bool

// Immutable operations (return new set)
$set->withAdded($item1, $item2)     // Add items
$set->withRemoved($item)            // Remove items
$set->union($otherSet)              // Set union
$set->intersect($otherSet)          // Set intersection
$set->diff($otherSet)               // Set difference

// Conversion
$set->values()                       // → list<T>
```

### Deque - Double-Ended Queue
```php
$deque = new Deque();

$deque->pushFront($value)           // Add to front
$deque->pushBack($value)            // Add to back
$deque->popFront()                  // → T (throws UnderflowException)
$deque->popBack()                   // → T (throws UnderflowException)
$deque->peekFront()                 // → T (throws UnderflowException)
$deque->peekBack()                  // → T (throws UnderflowException)

$deque->size()                       // → int
$deque->isEmpty()                    // → bool
$deque->clear()                      // Remove all items
$deque->toArray()                    // → list<T> (front to back)
```

### Buffer - FIFO Buffers
```php
// ArrayBuffer — unbounded, array-based
$buf = new ArrayBuffer();

// SimpleRingBuffer — fixed-size circular, overwrites oldest when full
$buf = new SimpleRingBuffer(capacity: 100);

// Shared API (BufferInterface)
$buf->push($value)                   // Append item
$buf->pop()                          // → T (FIFO, throws UnderflowException)
$buf->count()                        // → int
$buf->isEmpty()                      // → bool
$buf->toArray()                      // → list<T> (oldest to newest)

// BoundedBufferInterface (SimpleRingBuffer only)
$buf->isFull()                       // → bool
$buf->capacity()                     // → int
```

### TagMap - Tagged Collection
```php
// ImmutableTagMap — class-indexed, O(1) class lookup
$tags = ImmutableTagMap::create([$tag1, $tag2])
$tags = ImmutableTagMap::empty()

// IndexedTagMap — sequential IDs, dual-indexed
$tags = IndexedTagMap::create([$tag1, $tag2])
$tags = IndexedTagMap::empty()

// Shared API (TagMapInterface)
$tags->add($tag1, $tag2)            // → new TagMap with tags appended
$tags->replace($tag1, $tag2)        // → new TagMap with only these tags
$tags->has(TimingTag::class)         // → bool
$tags->isEmpty()                     // → bool
$tags->getAllInOrder()               // → TagInterface[]
$tags->merge($otherTagMap)           // → merged TagMap
$tags->mergeInto($targetTagMap)      // → merged into target
$tags->query()                       // → TagQuery (fluent interface)

// ImmutableTagMap extras
$tags->count()                       // → int (optionally by class)
$tags->count(TimingTag::class)       // → int (tags of that class)
$tags->last(TimingTag::class)        // → TagInterface|null
```

### TagQuery - Fluent Tag Querying
```php
$q = $tags->query()

// Chainable transformations
$q->ofType(TimingTag::class)         // Filter by instanceof
$q->only(TagA::class, TagB::class)   // Keep only named classes
$q->without(ErrorTag::class)         // Exclude classes
$q->filter(fn($tag) => $tag->x > 0) // Custom predicate
$q->map(fn($tag) => transform($tag)) // Transform tags
$q->limit(5)                        // Take first N
$q->skip(2)                         // Skip first N

// Terminal operations
$q->all()                           // → TagInterface[]
$q->get()                           // → TagMapInterface
$q->first()                         // → TagInterface|null
$q->last()                          // → TagInterface|null
$q->count()                         // → int
$q->has(TimingTag::class)           // → bool (class or instance)
$q->hasAll(TagA::class, TagB::class) // → bool
$q->hasAny(TagA::class, TagB::class) // → bool
$q->any(fn($tag) => $tag->x > 0)   // → bool (any match predicate)
$q->every(fn($tag) => $tag->x > 0) // → bool (all match predicate)
$q->isEmpty()                       // → bool
$q->isNotEmpty()                    // → bool
$q->classes()                       // → class-string[]
$q->mapTo(fn($tag) => $tag->value) // → array<mixed>
$q->reduce(fn($acc, $tag) => ..., $init) // → mixed
```

## Caching & Lazy Loading

### Cached - Lazy Value Container
```php
// Creation
Cached::from(fn() => expensiveOperation()) // Lazy evaluation
Cached::fromValue($value)           // Pre-resolved value

// Usage
$cached->get(...$args)               // Resolve once, cache result
$cached->isResolved()                // → bool
$cached(...$args)                    // Invoke syntax

// String representation
echo $cached;                        // Safe debug output
```

Notes:
- `Cached` is `final` and immutable. There is no `fresh()` or reset method.

## Utilities

### Uuid - ID Generation
```php
Uuid::uuid4()                        // → "550e8400-e29b-41d4-a716-446655440000"
Uuid::hex(8)                         // → random hex string (16 chars)
Uuid::isValid($string)              // → bool (validates UUID v1-v5 format)
Uuid::assertValid($string)          // throws InvalidArgumentException if invalid
```


### Time - Clock Abstractions
```php
// ClockInterface — single method: now(): DateTimeImmutable

// SystemClock — real wall clock
$clock = new SystemClock();
$clock->now()                        // → DateTimeImmutable (current time)

// FrozenClock — always returns same time (readonly, for tests)
FrozenClock::create()                // Freeze at current time
FrozenClock::at('2025-01-01 12:00')  // Freeze at specific time
FrozenClock::atEpoch()               // Freeze at Unix epoch
$frozen->now()                       // → always the same DateTimeImmutable

// VirtualClock — manually controllable time (for tests)
VirtualClock::at('2025-06-15 09:00') // Start at specific time
VirtualClock::atEpoch()              // Start at Unix epoch
$vclock->now()                       // → DateTimeImmutable
$vclock->setTime($dateTime)          // Jump to specific time → self
$vclock->advance(60)                 // Move forward N seconds → self
$vclock->rewind(30)                  // Move backward N seconds → self
$vclock->advanceBy('+2 hours')       // Move by interval string → self
$vclock->reset($timestamp)           // Reset to Unix timestamp → self
$vclock->timestamp()                 // → int (Unix timestamp)
```

### Profiler - Performance Measurement
```php
// Static interface
Profiler::mark('operation')          // → Checkpoint
Profiler::mark('op', ['key' => 'v']) // → Checkpoint with context
Profiler::delta()                    // → float (time since last mark)
Profiler::summary()                  // → string (timing report)

// Instance methods
$profiler = Profiler::get();         // → Profiler (singleton)
$profiler->addMark('step', $context) // → Checkpoint
$profiler->timeSinceLast()           // → float (seconds)
$profiler->getTotalTime()            // → float (microseconds)
$profiler->getSummary()              // → string (timing report)
$profiler->getFirst()                // → Checkpoint
$profiler->getLast()                 // → Checkpoint
$profiler->diff($checkpointA, $checkpointB) // → float (seconds)

// Checkpoint properties & methods
$cp->name                           // string
$cp->time                           // float (microtime)
$cp->delta                          // float (seconds since previous)
$cp->context                        // array
$cp->mili()                         // → float (delta in milliseconds)
$cp->micro()                        // → float (delta in microseconds)
```

## XML Processing

### Xml - XML to Array/Object
```php
// Creation & parsing
Xml::from($xmlString)
    ->withTags(['tag1', 'tag2'])     // Parse only specific tags
    ->wrapped('root')                // Wrap in root element

// Output
$xml->toArray()                      // → array structure
$xml->toXmlElement()                 // → XmlElement object
```

### XmlElement - XML DOM Node
```php
// Creation
XmlElement::fromArray(['tag' => 'div', 'content' => '', 'attributes' => [], 'children' => []])
new XmlElement($tag, $content, $attributes, $children)

// Accessors
$el->tag()                           // → string
$el->content()                       // → string
$el->attributes()                    // → array<string, string>
$el->children()                      // → XmlElement[]
$el->attribute('name', $default)     // → ?string

// Traversal
$el->first('tagName')                // → ?XmlElement (first child with tag)
$el->all('tagName')                  // → XmlElement[] (children with tag)
$el->get('0.1')                      // → XmlElement (dot-notation index path)

// State checks
$el->hasChildren()                   // → bool
$el->hasContent()                    // → bool

// Conversion
$el->toArray()                       // → array
```

### SimpleXmlParser - SimpleXML-based Parser
```php
// Creation & fluent config
SimpleXmlParser::from($xmlString)
    ->withAttributes()               // Include attributes in output
    ->withRoot()                     // Include root element in output
    ->wrapped('root')                // Wrap in root element
    ->asCamelCase()                  // Keys as camelCase
    ->asSnakeCase()                  // Keys as snake_case
    ->withNaming('raw')              // Custom naming convention
    ->toArray()                      // → array<string, mixed>
```

### XmlValidator - XML Validation
```php
$validator = new XmlValidator();
$validator->validate($xmlString);    // throws RuntimeException on invalid XML
```

## JSON Schema

### JsonSchema - Schema Definition
```php
// Type factory methods (all accept: $name, $description, $title, $nullable, $meta)
JsonSchema::string('field')
JsonSchema::integer('field')
JsonSchema::number('field')
JsonSchema::boolean('field')
JsonSchema::any('field')
JsonSchema::enum('status', ['active', 'inactive'])

// Object schema
JsonSchema::object(
    name: 'User',
    properties: [JsonSchema::string('id'), JsonSchema::string('name')],
    requiredProperties: ['id', 'name'],
    description: 'User object',
    additionalProperties: false,
)

// Array/collection schema
JsonSchema::array('tags', itemSchema: JsonSchema::string())
JsonSchema::collection('users', itemSchema: JsonSchema::object('User', ...))

// From existing data
JsonSchema::fromArray($schemaArray, 'fieldName', required: true)
JsonSchema::document($rawSchemaArray)  // Preserves raw anyOf/oneOf/allOf on roundtrip

// Fluent mutation (all return new immutable copy)
$schema->withName('newName')
$schema->withDescription('desc')
$schema->withTitle('title')
$schema->withNullable(true)
$schema->withMeta(['key' => 'value'])
$schema->withEnumValues(['a', 'b'])
$schema->withProperties([...])
$schema->withItemSchema(JsonSchema::string())
$schema->withRequiredProperties(['id'])
$schema->withAdditionalProperties(false)
$schema->withRef('#/$defs/Address')
$schema->withDefs(['Address' => $addressSchema])
$schema->withDef('Address', $addressSchema)

// Accessors
$schema->type()                      // → JsonSchemaType
$schema->name()                      // → string
$schema->description()               // → string
$schema->title()                     // → string
$schema->isNullable()                // → bool
$schema->properties()                // → JsonSchema[]
$schema->property('name')            // → ?JsonSchema
$schema->requiredProperties()        // → array<string>
$schema->additionalProperties()      // → ?bool
$schema->hasAdditionalProperties()   // → bool
$schema->enumValues()                // → array<int|string>
$schema->hasEnumValues()             // → bool
$schema->itemSchema()                // → ?JsonSchema
$schema->hasItemSchema()             // → bool
$schema->itemType()                  // → ?JsonSchemaType
$schema->ref()                       // → ?string
$schema->hasRef()                    // → bool
$schema->defs()                      // → array<string, JsonSchema>
$schema->hasDefs()                   // → bool
$schema->def('Address')              // → ?JsonSchema
$schema->meta('key', $default)       // → mixed (null key returns all meta)
$schema->hasMeta('key')              // → bool
$schema->hasDefaultValue()           // → bool
$schema->defaultValue()              // → mixed
$schema->objectClass()               // → ?string (from x-php-class meta)

// Type checks
$schema->isObject()                  // → bool
$schema->isArray()                   // → bool (untyped array)
$schema->isCollection()              // → bool (typed array with item schema)
$schema->isString()                  // → bool
$schema->isInteger()                 // → bool
$schema->isNumber()                  // → bool
$schema->isBoolean()                 // → bool
$schema->isNull()                    // → bool
$schema->isAny()                     // → bool
$schema->isEnum()                    // → bool
$schema->isOption()                  // → bool (string enum without class)
$schema->isScalar()                  // → bool
$schema->isScalarCollection()        // → bool
$schema->isEnumCollection()          // → bool
$schema->isObjectCollection()        // → bool
$schema->isOptionCollection()        // → bool

// Transformation
$schema->toArray()                   // → array (JSON Schema array)
$schema->toJsonSchema()              // → array (alias for toArray)
$schema->toString()                  // → string (JSON-encoded)
$schema->toFunctionCall('name', 'desc', strict: false) // → OpenAI function call format
$schema->toResponseFormat('name', 'desc', strict: true) // → OpenAI response format
```

### ToolSchema - Function Tool Wrapper
```php
// Creation
ToolSchema::make('tool_name', 'description', $jsonSchema)
ToolSchema::fromArray($data)
new ToolSchema('tool_name', 'description', $jsonSchema)

// Properties (readonly)
$tool->name                          // → string
$tool->description                   // → string
$tool->parameters                    // → JsonSchema

// Conversion
$tool->toArray()                     // → array (OpenAI function tool format)
```

### CanProvideJsonSchema - Contract
```php
interface CanProvideJsonSchema {
    public function toJsonSchema() : array;
}
```

## CLI Utilities

### Console - Terminal Output
```php
// Basic output
Console::print('message', Color::RED)       // Print with color
Console::println('message', [Color::BOLD, Color::BLUE]) // Print with newline

// Layout
Console::center('text', 80, Color::GREEN)   // → centered string
Console::columns([                           // → formatted columns string
    [20, 'Col 1', STR_PAD_RIGHT],
    [-1, 'Col 2', STR_PAD_LEFT]             // -1 = remaining width
], 80)
Console::printColumns($columns, 80, ' ')    // Print columns directly

// Utilities
Console::clearScreen()
Console::getWidth()                          // → int (terminal width)
```

### Color - ANSI Color Constants
```php
// Bright foreground
Color::RED, Color::GREEN, Color::BLUE, Color::YELLOW
Color::MAGENTA, Color::CYAN, Color::WHITE, Color::DARK_GRAY
// Dark foreground
Color::BLACK, Color::DARK_RED, Color::DARK_GREEN, Color::DARK_YELLOW
Color::DARK_BLUE, Color::DARK_MAGENTA, Color::DARK_CYAN, Color::GRAY
// Background
Color::BG_BLACK, Color::BG_RED, Color::BG_GREEN, Color::BG_YELLOW
Color::BG_BLUE, Color::BG_MAGENTA, Color::BG_CYAN, Color::BG_WHITE, Color::BG_GRAY
// Styles
Color::BOLD, Color::ITALICS, Color::RESET, Color::CLEAR
// Use in arrays for combination: [Color::BOLD, Color::RED]
```

## Text & Code Utilities

### TextRepresentation - Convert Any Value to String
```php
TextRepresentation::fromAny($input)  // string|array|object → string
// Tries: string passthrough, array→JSON, ->toJson(), ->toArray(), ->toString(),
//        BackedEnum->value, Closure invocation, fallback JSON encode

TextRepresentation::fromParameter($value, $key, $params) // Parameter-aware conversion
// Additional support: ->toSchema(), ->toOutputSchema(), ->value(), callable($key, $params)
```

### Tokenizer - GPT-3 Token Counting
```php
Tokenizer::tokenCount('some text')   // → int (number of tokens)
```

### ProgrammingLanguage - Language Enum & Helpers
```php
// Enum cases: Bash, C, Cpp, Go, Java, JavaScript, Lua, Perl, Php, Python, Ruby, Shell, SQL, TypeScript
$lang = ProgrammingLanguage::Php;
$lang->value                                    // → 'php'
$lang->extension()                              // → 'php'

// Static helpers (accept any language string, not just enum values)
ProgrammingLanguage::fileExtension('python')    // → 'py'
ProgrammingLanguage::commentSyntax('python')    // → '#'
ProgrammingLanguage::fileTemplate('php')        // → doctest template string
ProgrammingLanguage::isCommentLine('php', '// comment') // → true
ProgrammingLanguage::linesOfCode('php', $code)  // → int (non-empty, non-comment)
```

## Data Helpers

### Metadata - Immutable Key-Value Store
```php
// Creation
new Metadata(['key' => 'value'])
Metadata::empty()
Metadata::fromArray($array)

// Access
$meta->get('key', 'default')        // → mixed
$meta->hasKey('key')                 // → bool
$meta->keys()                        // → array
$meta->isEmpty()                     // → bool
$meta->count()                       // → int (Countable)

// Immutable mutations (return new Metadata)
$meta->withKeyValue('key', 'val')    // Add/replace key
$meta->withoutKey('key')             // Remove key
$meta->withMergedData(['k' => 'v'])  // Merge array

// Conversion
$meta->toArray()                     // → array
// Also: iterable (IteratorAggregate)
```

### OpaqueExternalId - Abstract Typed Identifier
```php
// Extend to create domain-specific IDs: class UserId extends OpaqueExternalId {}
$id = UserId::fromString('abc-123')  // Create from string
UserId::empty()                      // Empty ID (value = '')
UserId::null()                       // Alias for empty()

$id->value                           // Public readonly string property
$id->isEmpty()                       // → bool (true if blank)
$id->isPresent()                     // → bool (opposite of isEmpty)
$id->toString()                      // → string
$id->toNullableString()              // → string|null (null if empty)
$id->equals($otherId)                // → bool (same class + same value)
(string) $id                         // Stringable support
```

### AbstractResolver - Priority-Based Provider Chain
```php
// Extend and implement accepts(mixed $candidate): bool
// Constructor: new MyResolver([$provider1, $provider2], suppressErrors: true)
// Providers: callables or objects, evaluated lazily, first acceptable wins
// Call resolve() from subclass to get cached result (throws if none accepted)
```

## Markdown

### FrontMatter - YAML Front Matter Parser
```php
// Parse markdown with optional YAML front matter
$fm = FrontMatter::parse($text);     // Static factory (private constructor)

// Accessors
$fm->data()                          // → array (parsed YAML key-value map)
$fm->document()                      // → string (content after front matter)
$fm->hasFrontMatter()                // → bool (true if --- delimiters found)
$fm->error()                         // → ?string (YAML parse error message)
```

## Specialized Parsers

### JsonExtractor - JSON Extraction from Text
```php
use Cognesy\Utils\Json\JsonExtractor;

JsonExtractor::first($text)          // → ?array (first valid JSON object/array, or null)
JsonExtractor::all($text)            // → list<array> (all valid JSON objects/arrays)
// Tries: raw input, markdown fenced blocks, brace-matching scan
```

### JsonDecoder - Resilient JSON Decoder
```php
use Cognesy\Utils\Json\JsonDecoder;

JsonDecoder::decode($input)          // → mixed (handles valid, repairable, and broken JSON)
JsonDecoder::decodeToArray($input)   // → array ([] on failure or non-array result)
// Strategy: json_decode fast path → minimal repairs → JsonExtractor → tolerant tokenizer
```

### IncrementalJsonParser - Streaming Chunk Parser
```php
use Cognesy\Utils\Json\IncrementalJsonParser;

$parser = new IncrementalJsonParser();
$parser->append($chunk)             // Feed next chunk of data
$parser->buffer()                    // → string (raw accumulated input)
$parser->currentJson()               // → ?string (completed JSON so far, or null)
$parser->currentArray()              // → ?array (decoded array so far, or null)
$parser->completionSuffix()          // → ?string (closing tokens needed, or null)
$parser->reset()                     // Clear all state for reuse
```

### XML Parsers
```php
// SelectiveXmlParser - parse only specified tags (used internally by Xml)
$parser = new SelectiveXmlParser(['tag1', 'tag2']); // empty = parse all
$parser->parse($xmlContent)          // → array of parsed node arrays

// XmlValidator - validate XML structure
$validator = new XmlValidator();
$validator->validate($xmlString);    // throws RuntimeException on invalid XML
```

All classes follow immutable patterns where applicable and use strict typing throughout.
