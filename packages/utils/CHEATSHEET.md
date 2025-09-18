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
$json->toArray()                     // → array
$json->toString()                    // → string
$json->isEmpty()                     // → bool
$json->format(JSON_PRETTY_PRINT)    // → formatted string

// Static helpers
Json::decode('{"a":1}', [])          // → array with default fallback
Json::encode(['a' => 1])             // → string
```

### Result - Monadic Error Handling
```php
// Creation
Result::success($value)              // Success state
Result::failure($error)              // Failure state
Result::from($anyValue)              // Auto-wrap (Throwable → failure)
Result::try(fn() => riskyOperation()) // Catch exceptions

// Checking state
$result->isSuccess()                 // → bool
$result->isFailure()                 // → bool
$result->isSuccessAndNull()          // → bool
$result->valueOr('default')          // Get value or fallback

// Transformations
$result->map(fn($v) => transform($v)) // Transform success value
$result->then(fn($v) => Result::success(transform($v))) // Chain Results
$result->recover(fn($e) => fallback($e)) // Handle failure

// Side effects
$result->ifSuccess(fn($v) => log($v)) // Execute on success
$result->ifFailure(fn($e) => log($e)) // Execute on failure

// Batch operations
Result::tryAll($args, $callback1, $callback2) // Try multiple callbacks
Result::tryUntil($condition, $args, $callbacks) // Until condition met
```

### Str - String Operations
```php
// Case conversion
Str::pascal('hello_world')           // → HelloWorld
Str::snake('HelloWorld')             // → hello_world
Str::camel('hello-world')            // → helloWorld
Str::kebab('HelloWorld')             // → hello-world
Str::title('hello world')            // → Hello World

// Searching
Str::contains('text', 'needle', true) // Case sensitive search
Str::containsAll('text', ['a','b'])   // All needles present
Str::containsAny('text', ['a','b'])   // Any needle present
Str::startsWith('text', 'prefix')     // → bool
Str::endsWith('text', 'suffix')       // → bool

// Extraction
Str::between('a<content>b', '<', '>') // → content
Str::after('prefix:content', ':')     // → content
Str::limit('long text', 5, '...')     // → long...

// Conditionals
Str::when(true, 'yes', 'no')         // → yes
```

### Arrays - Array Utilities
```php
// Transformation
Arrays::asArray($value)              // Force to array
Arrays::map($array, fn($v,$k) => $v) // Map with key access
Arrays::flatten([$nested, $arrays])  // → flat array
Arrays::fromAny($object)             // Convert any type to array

// Manipulation
Arrays::mergeNull($arr1, $arr2)      // Merge handling nulls
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
Files::copyDirectory($src, $dst)     // Recursive copy (throws on error)
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
DataMap::fromJson('{"key":"value"}')

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

### Context - Service Container
```php
// Creation
Context::empty()
new Context(['Service' => $instance])

// Service management
$ctx->with(Service::class, $service) // → new Context (immutable)
$ctx->get(Service::class)            // → object (throws if missing)
$ctx->has(Service::class)            // → bool
$ctx->merge($otherContext)           // → new Context
```

## Caching & Lazy Loading

### Cached - Lazy Value Container
```php
// Creation
Cached::from(fn() => expensiveOperation()) // Lazy evaluation
Cached::withValue($value)            // Pre-resolved value

// Usage
$cached->get(...$args)               // Resolve once, cache result
$cached->isResolved()                // → bool
$cached->fresh()                     // Reset for re-computation
$cached(...$args)                    // Invoke syntax

// String representation
echo $cached;                        // Safe debug output
```

## Utilities

### Uuid - ID Generation
```php
Uuid::uuid4()                        // → "550e8400-e29b-41d4-a716-446655440000"
Uuid::hex(8)                         // → random hex string (16 chars)
```

### Instance - Dynamic Instantiation
```php
Instance::of(MyClass::class)
    ->withArgs(['param1', 'param2'])
    ->make()                         // → MyClass instance

// Supports both indexed and associative arrays for constructor args
```

### Profiler - Performance Measurement
```php
// Static interface
Profiler::mark('operation')          // → Checkpoint
Profiler::delta()                    // → float (time since last mark)
Profiler::summary()                  // Print timing report

// Instance methods
$profiler = Profiler::get();
$profiler->addMark('step', $context) // → Checkpoint  
$profiler->timeSinceLast()           // → float
$profiler->getTotalTime()            // → float (microseconds)
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

## JSON Schema

### JsonSchema - Schema Definition
```php
// Object schema
JsonSchema::object('User', 'User object', [
    JsonSchema::string('id'),
    JsonSchema::string('name'),
], ['id', 'name'])

// Array schema  
JsonSchema::array('users')
    ->withItemSchema(JsonSchema::string())
    ->withRequired(['id'])

// From existing data
JsonSchema::fromArray($schemaArray, 'fieldName', true)

// Access methods
$schema->toArray()                   // → array representation
$schema->getName()                   // → string
$schema->isRequired()                // → bool
```

## CLI Utilities

### Console - Terminal Output
```php
// Basic output
Console::print('message', Color::RED)
Console::println('message', [Color::BOLD, Color::BLUE])

// Layout
Console::center('text', 80, Color::GREEN) // Centered text
Console::columns([
    [20, 'Column 1', STR_PAD_RIGHT],
    [-1, 'Column 2', STR_PAD_LEFT]  // -1 = remaining width
], 80)

// Utilities
Console::clearScreen()
Console::getWidth()                  // → int (terminal width)
```

### Color - ANSI Color Constants
```php
Color::RED, Color::GREEN, Color::BLUE, Color::YELLOW
Color::BOLD, Color::UNDERLINE, Color::RESET
// Use in arrays for combination: [Color::BOLD, Color::RED]
```

## Specialized Parsers

### JSON Parsers
- `ResilientJsonParser` - Handles malformed JSON
- `PartialJsonParser` - Streams incomplete JSON
- `JsonParser` - Main parser with complete/partial methods

### XML Parsers  
- `SelectiveXmlParser` - Parse only specified tags
- `SimpleXmlParser` - Basic XML parsing
- `XmlValidator` - Validate XML structure

All classes follow immutable patterns where applicable and use strict typing throughout.