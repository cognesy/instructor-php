# Instructor PHP - Structured Output API Cheatsheet

## Core Classes

### `StructuredOutput` - Main facade class
**Purpose**: Entry point for structured output generation from LLM responses

**Key Methods**:
- `with(messages, responseModel, system?, prompt?, examples?, model?, maxRetries?, options?, toolName?, toolDescription?, retryPrompt?, mode?)` - Configure request parameters
- `withRequest(StructuredOutputRequest)` - Use pre-built request object
- `create()` - Returns `PendingStructuredOutput` instance
- `get()` - Execute and return parsed result
- `response()` - Execute and return raw LLM response
- `stream()` - Execute and return `StructuredOutputStream`
- `getString()`, `getFloat()`, `getInt()`, `getBoolean()`, `getObject()`, `getArray()` - Type-specific result getters

### `PendingStructuredOutput` - Request execution handler
**Purpose**: Handles request execution and response processing

**Key Methods**:
- `get()` - Execute request and return parsed result
- `response()` - Execute request and return raw LLM response
- `stream()` - Execute request and return streaming response
- `toJson()` - Convert result to JSON string
- `toArray()` - Convert result to array
- `toJsonObject()` - Convert result to Json object

### `StructuredOutputStream` - Streaming response handler
**Purpose**: Process streaming LLM responses with real-time updates

**Key Methods**:
- `partials()` - Iterable of partial updates
- `finalValue()` - Get final parsed result
- `finalResponse()` - Get final LLM response
- `sequence()` - Iterate over sequence updates
- `responses()` - Generator of partial LLM responses
- `lastUpdate()` - Get last received update
- `lastResponse()` - Get last received LLM response
- `usage()` - Get token usage statistics

## Structured Data Types

### `Example` - Input/output examples
**Purpose**: Manage training examples for LLM context

**Key Methods**:
- `fromChat(messages, output)` - Create from chat messages
- `fromText(input, output)` - Create from text input
- `fromJson(json)` - Create from JSON data
- `fromArray(data)` - Create from array data
- `input()`, `output()` - Access input/output data
- `toArray()`, `toString()`, `toMessages()` - Convert to various formats

### `Maybe<T>` - Optional value wrapper
**Purpose**: Handle optional values with error information

**Key Methods**:
- `is(class, name?, description?)` - Static factory method
- `get()` - Get value or null
- `error()` - Get error message
- `hasValue()` - Check if value exists
- `toJsonSchema()` - Generate JSON schema

### `Scalar` - Simple value extraction
**Purpose**: Extract scalar values with validation

**Key Properties**:
- `$value` - The scalar value
- `$name` - Field name
- `$description` - Field description
- `$type` - ValueType (STRING, INTEGER, FLOAT, BOOLEAN, ENUM)
- `$required` - Whether field is required
- `$defaultValue` - Default value

### `Sequence<T>` - Array/list handling
**Purpose**: Manage sequences of objects with array access

**Key Methods**:
- `of(class, name?, description?)` - Static factory method
- `all()` - Get all items
- `count()` - Get item count
- `get(index)` - Get item by index
- `first()`, `last()` - Get first/last item
- `push(item)`, `pop()` - Add/remove items
- `isEmpty()` - Check if empty
- Array access via `[]` notation

### `Structure` - Dynamic object creation
**Purpose**: Create structured objects with field definitions

**Key Methods**:
- `define(name, fields, description?)` - Static factory method
- `has(field)` - Check if field exists
- `field(name)` - Get field definition
- `fields()` - Get all fields
- `get(field)`, `set(field, value)` - Access field values
- `count()` - Get field count
- `asScalar()` - Convert to scalar if single field
- Magic methods: `__get()`, `__set()`, `__isset()`

### `Field` - Structure field definitions
**Purpose**: Define individual fields within structures

**Key Static Methods**:
- `int(name, description?)` - Integer field
- `string(name, description?)` - String field
- `float(name, description?)` - Float field
- `bool(name, description?)` - Boolean field
- `enum(name, enumClass, description?)` - Enum field
- `option(name, values, description?)` - Option field
- `object(name, class, description?)` - Object field
- `datetime(name, description?)` - DateTime field
- `structure(name, fields, description?)` - Nested structure
- `collection(name, itemType, description?)` - Collection field
- `array(name, description?)` - Array field

### `StructureFactory` - Structure creation factory
**Purpose**: Create structures from various sources

**Key Methods**:
- `fromClass(class, name?, description?)` - Create from PHP class

## Usage Patterns

### Basic Usage
```php
$structuredOutput = new StructuredOutput();
$result = $structuredOutput
    ->with(messages: "Extract person data", responseModel: Person::class)
    ->get();
```

### Streaming Usage
```php
$stream = $structuredOutput
    ->with(messages: "Generate list", responseModel: PersonList::class)
    ->stream();
    
foreach ($stream->partials() as $partial) {
    // Process partial updates
}
```

### Dynamic Structures
```php
$structure = Structure::define('person', [
    Field::string('name', 'Full name'),
    Field::int('age', 'Age in years'),
    Field::enum('status', Status::class, 'Current status')
]);

$result = $structuredOutput
    ->with(messages: "Extract person", responseModel: $structure)
    ->get();
```

### Optional Values
```php
$maybe = Maybe::is(Person::class, 'person', 'Person data if found');
$result = $structuredOutput->with(messages: "Find person", responseModel: $maybe)->get();
if ($result->hasValue()) {
    $person = $result->get();
}
```

### Sequences
```php
$sequence = Sequence::of(Person::class, 'people', 'List of people');
$result = $structuredOutput->with(messages: "Extract people", responseModel: $sequence)->get();
foreach ($result as $person) {
    // Process each person
}
```