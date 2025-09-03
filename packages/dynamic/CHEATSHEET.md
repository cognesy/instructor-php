# Dynamic Package - Deep Reference

## Core Architecture

### Dynamic Structure System
```php
// Structure implements all major contracts for schema, validation, transformation, and deserialization
class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf, CanTransformSelf {
    protected string $name = '';
    protected string $description = '';
    /** @var Field[] */
    protected array $fields = [];
}

// Field represents individual data points with type safety and validation
class Field {
    // Schema, validation, value management, optionality, and collection handling
}
```

## Structure Definition Patterns

### Basic Definition
```php
// Static definition with field array
$user = Structure::define('User', [
    Field::string('name', 'User full name'),
    Field::int('age', 'User age')->optional(),
    Field::bool('active', 'Account status')->withDefaultValue(true)
], 'User information');

// Callable definition with builder pattern
$user = Structure::define('User', function($struct) {
    return [
        Field::string('name', 'User full name'),
        Field::int('age', 'User age')->optional(),
        Field::bool('active', 'Account status')->withDefaultValue(true)
    ];
}, 'User information');
```

### Field Type System
```php
// Scalar types
Field::int('count', 'Number of items');
Field::string('name', 'Item name');
Field::float('price', 'Item price');  
Field::bool('active', 'Status flag');

// Enum and option types
Field::enum('status', StatusEnum::class, 'Current status');
Field::option('category', ['A', 'B', 'C'], 'Category selection');

// Object types
Field::object('user', User::class, 'User object');
Field::datetime('created', 'Creation timestamp');

// Complex nested structures
Field::structure('address', [
    Field::string('street', 'Street address'),
    Field::string('city', 'City name'),
    Field::string('country', 'Country code')
], 'Address information');

// Collections
Field::collection('tags', 'string', 'List of tags');
Field::collection('users', User::class, 'List of users');
Field::collection('items', $itemStructure, 'List of structured items');
Field::array('metadata', 'Raw array data');
```

### Field Configuration Chains
```php
Field::string('email', 'User email')
    ->required(true)  // or ->optional(true)
    ->withDefaultValue('guest@example.com')
    ->validIf(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL), 'Invalid email format')
    ->validator(fn($value) => ValidationResult::valid());
```

## Structure Factory Patterns

### From Array Key-Values
```php
// Creates structure from existing data
$data = ['name' => 'John', 'age' => 30, 'active' => true];
$structure = StructureFactory::fromArrayKeyValues('User', $data, 'User from array');
// All fields automatically marked as optional
```

### From Class Reflection  
```php
// Extract structure from existing class
$structure = StructureFactory::fromClass(User::class, 'UserStruct', 'User structure');

// Skips: static properties, readonly properties, properties with #[Ignore] attribute
// Maps: public properties with type hints, descriptions from docblocks
// Handles: nullable properties as optional fields
```

### From Callable Signatures
```php
// Function signature analysis
$structure = StructureFactory::fromFunctionName('calculateTax', 'TaxInput', 'Tax calculation input');
$structure = StructureFactory::fromMethodName(Calculator::class, 'compute', 'ComputeInput');
$structure = StructureFactory::fromCallable($closure, 'ClosureInput', 'Closure parameters');

// Handles: optional parameters, default values, variadic parameters (as collections)
```

### From String Definitions
```php
// Simple field:type format
$structure = StructureFactory::fromString('User', 'name:string, age:int, active:bool');

// Array syntax with descriptions
$structure = StructureFactory::fromString('User', 
    'array{name: string (Full name), age: int (User age), active: bool (Status)}'
);

// Parse format: field:type (description), field:type (description)
```

### From JSON Schema
```php
$jsonSchema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string', 'description' => 'User name'],
        'age' => ['type' => 'integer', 'description' => 'User age']
    ],
    'required' => ['name']
];
$structure = StructureFactory::fromJsonSchema($jsonSchema);
```

### From Schema Objects
```php
$schema = (new SchemaFactory)->objectSchema($typeDetails, $name, $description);
$structure = StructureFactory::fromSchema('CustomStruct', $schema, 'From schema');
```

## Field Factory Type Resolution

### Automatic Type Mapping
```php
// FieldFactory::fromTypeDetails resolves TypeDetails to appropriate Field types
$field = FieldFactory::fromTypeName('count', 'int', 'Item count');
$field = FieldFactory::fromTypeDetails('user', $typeDetails, 'User object');

// Type resolution logic:
match (true) {
    $typeDetails->isInt() => Field::int($name, $description),
    $typeDetails->isString() => Field::string($name, $description),
    $typeDetails->isFloat() => Field::float($name, $description),
    $typeDetails->isBool() => Field::bool($name, $description),
    $typeDetails->isEnum() => Field::enum($name, $typeDetails->class, $description),
    $typeDetails->isCollection() => Field::collection($name, $typeDetails->nestedType, $description),
    $typeDetails->isArray() => Field::array($name, $description),
    $typeDetails->isObject() => Field::object($name, $typeDetails->class, $description),
    $typeDetails->isMixed() => Field::string($name, $description), // fallback
}
```

## Structure Access and Manipulation

### Field Access Patterns
```php
// Dynamic access
if ($structure->has('email')) {
    $email = $structure->get('email');
    $structure->set('email', 'new@example.com');
}

// Magic methods
$email = $structure->email;
$structure->email = 'new@example.com';
isset($structure->email);

// Field metadata
$field = $structure->field('email');
$typeDetails = $structure->typeDetails('email');
$fieldNames = $structure->fieldNames();
$fieldValues = $structure->fieldValues(); // name => value pairs

// Structure info
$count = $structure->count();
$isScalar = $structure->actsAsScalar(); // true if single field
$scalarValue = $structure->asScalar(); // extract single field value
```

### Collection Prototypes
```php
// Structure prototypes for collections
$itemPrototype = Structure::define('Item', [
    Field::string('name', 'Item name'),
    Field::float('price', 'Item price')
]);

$field = Field::collection('items', $itemPrototype, 'Shopping items');
$field->hasPrototype(); // true
$prototype = $field->prototype(); // returns Structure
$clone = $field->clone(); // deep clone with separate state
```

## Serialization and Deserialization

### Structure Serialization
```php
$array = $structure->toArray();
$json = json_encode($structure->toArray());

// Serialization logic per field type:
match(true) {
    ($field->typeDetails()->type === TypeDetails::PHP_ENUM) => $value->value,
    ($field->typeDetails()->type === TypeDetails::PHP_ARRAY) => $this->serializeArrayField($value),
    ($field->typeDetails()->type === TypeDetails::PHP_COLLECTION) => $this->serializeArrayField($value),
    ($field->typeDetails()->class == Structure::class) => $value?->toArray(),
    ($field->typeDetails()->class !== null) => $this->serializeObjectField($value),
    default => $value,
}
```

### Structure Deserialization
```php
// From JSON/Array
$structure = $structure->fromJson($jsonString);
$structure = $structure->fromArray($dataArray);

// Deserialization by field type:
match(true) {
    ($type->isEnum()) => ($type->class)::from($fieldData),
    ($type->isCollection()) => $this->deserializeCollection($field, $fieldData),
    ($type->isArray()) => is_array($fieldData) ? $fieldData : [$fieldData],
    ($type->class() === Structure::class) => $structure->get($name)->fromArray($fieldData),
    ($type->class() === DateTime::class) => new DateTime($fieldData),
    ($type->class() === DateTimeImmutable::class) => new DateTimeImmutable($fieldData),
    default => $this->deserializer->fromArray($fieldData, $type->class()),
}

// Collection item deserialization with prototype cloning
$clone = $field->prototype()?->clone()->fromArray($itemData);
```

## Validation System

### Structure-Level Validation
```php
$structure = $structure->validator(function($struct) {
    // Custom validation logic
    if ($struct->age < 0) {
        return ValidationResult::invalid('Age must be positive');
    }
    return ValidationResult::valid();
});

$result = $structure->validate();
if ($result->isInvalid()) {
    $errors = $result->getErrors();
}
```

### Field-Level Validation
```php
// Simple boolean validator
Field::string('email', 'Email address')
    ->validIf(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL), 'Invalid email format');

// Full ValidationResult validator  
Field::int('age', 'User age')
    ->validator(function($value) {
        if ($value < 0) {
            return ValidationResult::fieldError('age', $value, 'Age must be positive');
        }
        return ValidationResult::valid();
    });

// Validation execution
$result = $field->validate();
```

### Validation Result Merging
```php
// Structure validation merges all field validation results
$result = ValidationResult::merge($failedValidations, "Validation failed for fields: " . implode(', ', $invalidFields));
```

## Schema Generation

### Schema Export
```php
// Generate Schema objects
$schema = $structure->schema();
$schema = $structure->toSchema();

// JSON Schema export  
$jsonSchema = $structure->toJsonSchema();

// Schema construction for objects
$schema = new ObjectSchema(
    type: TypeDetails::object(Structure::class),
    name: $this->name(),
    description: $this->description(),
    properties: $properties,      // field schemas
    required: $required,          // required field names
);
```

## Advanced Patterns

### Deep Cloning with State Preservation
```php
$cloned = $structure->clone();
// Clones: name, description, validator, all fields recursively
// Preserves: field values, default values, optionality settings
// Separate: prototypes get cloned to avoid shared state
```

### Transformation and Contracts
```php
// Structure implements multiple contracts
interface CanProvideSchema  // ->schema(), ->toJsonSchema()
interface CanDeserializeSelf // ->fromJson(), ->fromArray()  
interface CanValidateSelf   // ->validate()
interface CanTransformSelf  // ->transform() (returns $this)

// Uses SymfonyDeserializer for object deserialization
$structure = new Structure(); // automatically sets deserializer
```

### Field Value Management
```php
// Value states
$field->set($value);
$value = $field->get();          // returns value or defaultValue
$isEmpty = $field->isEmpty();    // null or empty check
$hasDefault = $field->hasDefaultValue();
$default = $field->defaultValue();

// Required/Optional handling in deserialization
if ($field->isRequired() && empty($fieldData)) {
    throw new \Exception("Required field `$name` is empty.");
}
```

### Collection Item Type Resolution
```php
// Collection field creation with different item types
Field::collection('tags', 'string');              // string collection
Field::collection('counts', TypeDetails::int());   // TypeDetails collection  
Field::collection('items', $structurePrototype);   // Structure collection with prototype

// Nested collections not supported
($typeDetails->isCollection()) => throw new Exception('Nested collections are not supported.');
```

### Structure Metadata and Info
```php
$structure->withName('NewName')->withDescription('New description');
$name = $structure->name();
$description = $structure->description();

// Field naming and description updates propagate to nested structures
$field->withName('newName')->withDescription('New description');
// Updates nested Structure name/description if field->isStructure()
```