# Schema Package - Deep Reference

## Core Architecture

### Schema System Contracts
```php
interface CanProvideSchema {
    public function toSchema(): Schema;
}

interface CanAcceptSchemaVisitor {
    public function accept(CanVisitSchema $visitor): void;
}

interface CanVisitSchema {
    public function visitSchema(Schema $schema): void;
    public function visitObjectSchema(ObjectSchema $schema): void;
    public function visitCollectionSchema(CollectionSchema $schema): void;
    // ... all schema type visits
}
```

### TypeDetails - Core Type System
```php
class TypeDetails {
    public string $type;              // PHP type constant
    public ?string $class;            // For objects/enums
    public ?TypeDetails $nestedType;  // For collections
    public ?string $enumType;         // Enum backing type
    public ?array $enumValues;        // Enum possible values
    public ?string $docString;        // Documentation
}
```

## PHP Type Constants and Classification

### Type Constants Hierarchy
```php
// Core PHP types
const PHP_MIXED = 'mixed';
const PHP_OBJECT = 'object';
const PHP_ENUM = 'enum';
const PHP_COLLECTION = 'collection';     // Typed arrays: Type[]
const PHP_ARRAY = 'array';               // Untyped arrays
const PHP_SHAPE = 'shape';               // Array shapes
const PHP_INT = 'int';
const PHP_FLOAT = 'float';
const PHP_STRING = 'string';
const PHP_BOOL = 'bool';
const PHP_NULL = 'null';
const PHP_UNSUPPORTED = null;

// Type classifications
const PHP_SCALAR_TYPES = [PHP_INT, PHP_FLOAT, PHP_STRING, PHP_BOOL];
const PHP_OBJECT_TYPES = [PHP_OBJECT, PHP_ENUM];
const PHP_NON_SCALAR_TYPES = [PHP_OBJECT, PHP_ENUM, PHP_COLLECTION, PHP_ARRAY, PHP_SHAPE];
const PHP_ENUM_TYPES = [PHP_INT, PHP_STRING];

// gettype() to PHP type mapping
const TYPE_MAP = [
    "boolean" => PHP_BOOL,
    "integer" => PHP_INT,
    "double" => PHP_FLOAT,
    "string" => PHP_STRING,
    "array" => PHP_ARRAY,
    "object" => PHP_OBJECT,
    "resource" => PHP_UNSUPPORTED,
    "NULL" => PHP_UNSUPPORTED,
];
```

### TypeDetails Factory Methods
```php
// Direct type creation
TypeDetails::object(User::class);
TypeDetails::enum(Status::class, 'string', ['active', 'inactive']);
TypeDetails::option(['A', 'B', 'C']);          // String options without class
TypeDetails::collection('string');              // string[]
TypeDetails::collection(User::class);           // User[]
TypeDetails::array();                           // mixed[]
TypeDetails::int();
TypeDetails::string();
TypeDetails::bool();
TypeDetails::float();
TypeDetails::mixed();

// Dynamic creation
TypeDetails::fromTypeName('int');               // From string type
TypeDetails::fromTypeName('User[]');            // Collection from string
TypeDetails::fromPhpDocTypeString('array<User>'); // PHPDoc format
TypeDetails::fromValue($variable);              // Infer from value
TypeDetails::scalar('int');                     // Scalar type
TypeDetails::undefined();                       // Unsupported type

// Type inference from values
$value = ['a', 'b', 'c'];
$type = TypeDetails::fromValue($value);         // string[] if all same type
```

## TypeDetails Introspection API

### Type Checking Methods
```php
$type->isScalar();          // int, string, bool, float
$type->isMixed();           // mixed type
$type->isInt();             // int type
$type->isString();          // string type
$type->isBool();            // bool type  
$type->isFloat();           // float type
$type->isObject();          // object type
$type->isEnum();            // enum type
$type->isArray();           // array or collection
$type->isCollection();      // typed collection (Type[])

// Collection inspection
$type->isCollectionOf('string');       // string[]
$type->isCollectionOfScalar();         // scalar[]
$type->isCollectionOfObject();         // object[]
$type->isCollectionOfEnum();           // enum[]
$type->isCollectionOfArray();          // array[]

// Property checks
$type->hasNestedType();     // Has nested type definition
$type->hasClass();          // Has class name
$type->hasEnumType();       // Has enum backing type
```

### TypeDetails Access and Conversion
```php
$type->type();              // Main type string
$type->class();             // Class name (objects/enums)
$type->nestedType();        // Nested TypeDetails (collections)
$type->enumType();          // Enum backing type
$type->enumValues();        // Enum possible values
$type->docString();         // Documentation string

// Display methods
$type->shortName();         // Human-readable short name
$type->classOnly();         // Class basename (no namespace)

// Conversion
$type->toArray();           // Serialize to array
$type->clone();             // Deep clone with nested types
```

## Schema Class Hierarchy

### Base Schema Class
```php
class Schema implements CanAcceptSchemaVisitor {
    public string $name = '';
    public string $description = '';  
    public TypeDetails $typeDetails;
    
    // Visitor pattern
    public function accept(CanVisitSchema $visitor): void;
    
    // Type checking
    public function isScalar(): bool;
    public function isObject(): bool;
    public function isEnum(): bool;
    public function isArray(): bool;
    public function hasProperties(): bool;
    
    // Transformation
    public function toJsonSchema(): array;
    public function toArray(): array;
    public function clone(): self;
}
```

### Schema Specializations
```php
// Object schema with properties
class ObjectSchema extends Schema {
    /** @var array<string, Schema> */
    public array $properties = [];
    /** @var string[] */
    public array $required = [];
    
    // Provides property access trait
}

// Collection schema with nested items
class CollectionSchema extends Schema {
    public Schema $nestedItemSchema;
}

// Simple schemas
class ScalarSchema extends Schema {}
class EnumSchema extends Schema {}
class ArraySchema extends Schema {}     // Untyped array
class OptionSchema extends Schema {}    // String options
class MixedSchema extends Schema {}
class ArrayShapeSchema extends Schema {} // Typed array shape

// Reference schema for object references
class ObjectRefSchema extends Schema {}
```

## Schema Factory System

### SchemaFactory - Primary Schema Creation
```php
class SchemaFactory {
    protected bool $useObjectReferences = false;    // Inline vs referenced objects
    protected SchemaMap $schemaMap;                  // Schema caching
    protected PropertyMap $propertyMap;              // Property caching
    protected JsonSchemaToSchema $schemaConverter;   // JSON Schema conversion
}

// Universal schema creation
$factory = new SchemaFactory();
$schema = $factory->schema($input);

// Input type resolution
match(true) {
    $input instanceof Schema => $input,                    // Already a schema
    $input instanceof CanProvideSchema => $input->schema(), // Schema provider
    $input instanceof CanProvideJsonSchema => $this->schemaConverter->fromJsonSchema($input->toJsonSchema()),
    $input instanceof TypeDetails => /* create from TypeDetails */,
    is_string($input) => TypeDetails::fromTypeName($input), // Class/type name
    is_object($input) => TypeDetails::fromTypeName(get_class($input)), // Object instance
}
```

### Schema Creation from TypeDetails
```php
// Property schema creation
$schema = $factory->propertySchema($typeDetails, $name, $description);

// Internal schema mapping and caching
if (!$this->schemaMap->has($typeString)) {
    $this->schemaMap->register($typeString, $this->makeSchema($type));
}
return $this->schemaMap->get($anyType);
```

### TypeDetailsFactory - Type Resolution
```php
class TypeDetailsFactory {
    // Core type creation methods
    public function objectType(string $class): TypeDetails;
    public function enumType(string $class, ?string $backingType, ?array $values): TypeDetails;
    public function optionType(array $values): TypeDetails;
    public function collectionType(string $itemType): TypeDetails;
    public function arrayType(): TypeDetails;
    public function scalarType(string $type): TypeDetails;
    public function mixedType(): TypeDetails;
}

// String parsing with TypeString helper
$factory->fromPhpDocTypeString('array<User>');     // PHPDoc format
$factory->fromTypeName('User[]');                   // PHP array syntax
$factory->fromTypeName('?string');                  // Nullable types

// Value-based inference
$factory->fromValue($array);                        // Infer from actual value
// Logic: homogeneous arrays become collections, mixed become arrays
```

### JsonSchemaToSchema - JSON Schema Import
```php
class JsonSchemaToSchema {
    private $defaultToolName = 'extract_object';
    private $defaultToolDescription = 'Extract data from chat content';  
    private $defaultOutputClass = '';
    
    public function fromJsonSchema(array $jsonSchema): ObjectSchema;
}

// Requires x-php-class field for object/enum types
$converter = new JsonSchemaToSchema('CustomTool', 'Custom description', CustomClass::class);
$schema = $converter->fromJsonSchema($jsonSchemaArray);

// Schema type mapping from JSON Schema
match(true) {
    $json->isEnum() => $this->makeEnumOrOptionProperty($name, $json),
    $json->isObject() => $this->makeObjectProperty($name, $json),
    $json->isCollection() => $this->makeCollectionProperty($name, $json),
    $json->isArray() => $this->makeArrayProperty($name, $json),
    $json->isScalar() => $this->makeScalarProperty($name, $json),
}
```

## Visitor Pattern and Transformations

### SchemaToJsonSchema Visitor
```php
class SchemaToJsonSchema implements CanVisitSchema {
    private array $result = [];
    private $refCallback;           // Reference handling callback
    private string $defsLabel = '$defs';
    
    public function toArray(Schema $schema, ?callable $refCallback = null): array;
}

// Visitor methods for each schema type
visitScalarSchema(ScalarSchema $schema): void {
    $this->result = [
        'type' => $schema->typeDetails->toJsonType()->toString(),
        'description' => $schema->description,
        'enum' => $schema->typeDetails->enumValues,  // If applicable
    ];
}

visitObjectSchema(ObjectSchema $schema): void {
    // Special cases: DateTime -> string with format
    if (in_array($schema->typeDetails->class, [DateTime::class, DateTimeImmutable::class])) {
        $this->handleDateTimeSchema($schema);
        return;
    }
    
    // Standard object handling
    $propertyDefs = [];
    foreach ($schema->properties as $property) {
        $propertyDefs[$property->name] = (new SchemaToJsonSchema)->toArray($property, $this->refCallback);
    }
    
    $this->result = [
        'type' => 'object',
        'x-title' => $schema->name,
        'description' => $schema->description,
        'properties' => $propertyDefs,
        'required' => $schema->required,
        'x-php-class' => $schema->typeDetails->class,
        'additionalProperties' => false,
    ];
}

visitCollectionSchema(CollectionSchema $schema): void {
    $this->result = [
        'type' => 'array',
        'items' => (new SchemaToJsonSchema)->toArray($schema->nestedItemSchema, $this->refCallback),
        'description' => $schema->description,
    ];
}
```

### Object Reference Management
```php
visitObjectRefSchema(ObjectRefSchema $schema): void {
    $class = $this->className($schema->typeDetails->class);
    $id = "#/{$this->defsLabel}/{$class}";
    
    // Notify reference callback for definition collection
    if ($this->refCallback) {
        ($this->refCallback)(new Reference(
            id: $id,
            class: $schema->typeDetails->class,
            classShort: $class
        ));
    }
    
    $this->result = [
        '$ref' => $id,
        'description' => $schema->description,
        'x-php-class' => $schema->typeDetails->class,
    ];
}
```

## Reflection System

### ClassInfo - Class Introspection
```php
class ClassInfo {
    protected string $class;
    protected ReflectionClass $reflectionClass;
    protected array $propertyInfos = [];
    protected bool $isNullable = false;    // From ?Class syntax
}

// Factory method with enum detection
ClassInfo::fromString(string $class): self {
    return match(true) {
        class_exists($class) && is_subclass_of($class, \BackedEnum::class) => new EnumInfo($class),
        class_exists($class) => new ClassInfo($class),
        default => throw new Exception("Cannot create ClassInfo for `$class`"),
    };
}

// Property analysis
$classInfo->getProperties();            // PropertyInfo[]
$classInfo->getPropertyNames();         // string[]
$classInfo->getRequiredProperties();    // string[] (non-nullable, no defaults)
$classInfo->hasProperty($name);
$classInfo->isPublic($property);
$classInfo->isNullable($property);
$classInfo->isReadOnly($property);

// Constructor analysis
$classInfo->getConstructorInfo();       // ConstructorInfo
$classInfo->hasConstructor();
```

### PropertyInfo - Property Analysis
```php
class PropertyInfo {
    private ReflectionProperty $reflection;
    private CanGetPropertyType $typeInfoAdapter;  // V6/V7 compatibility
    
    // Type information
    public function getTypeDetails(): TypeDetails;
    public function isNullable(): bool;
    
    // Access patterns
    public function isPublic(): bool;
    public function isReadOnly(): bool;
    public function isStatic(): bool;
    public function isDeserializable(): bool;      // Can be set during deserialization
    
    // Requirement analysis
    public function isRequired(): bool;
}

// Complex requirement logic
isRequired() logic:
1. Constructor parameter match:
   - If has matching constructor param && param has no default && !nullable => required
2. Public property:
   - If public && !nullable => required  
3. Mutator method:
   - If has setter && setter param !nullable && setter param no default => required
```

### Property Requirement Detection
```php
// Multi-stage requirement detection
isRequired(): bool {
    // Case 1: Constructor parameter
    if ($this->matchesConstructorParam()) {
        $constructorParam = $this->getConstructorParam($this->propertyName);
        return !($constructorParam->isNullable() || $constructorParam->hasDefaultValue());
    }
    
    // Case 2: Public property
    if ($this->isPublic()) {
        return !$this->isNullable();
    }
    
    // Case 3: Mutator method
    if (!$this->isPublic() && $this->hasMutatorCandidates($this->propertyName)) {
        $mutatorParam = $this->getMutatorParam($this->propertyName);
        return !($this->isNullable() || $mutatorParam?->isNullable() || $mutatorParam?->hasDefaultValue());
    }
    
    return false;
}
```

### Method Detection Patterns
```php
// Mutator detection (setProperty, withProperty)
hasMutatorCandidates($propertyName): bool {
    $patterns = [
        fn($name) => 'set' . ucfirst($name),
        // fn($name) => 'with' . ucfirst($name),  // Commented out
    ];
    
    // Requirements: public, one parameter, returns void
}

// Accessor detection (getProperty, isProperty, hasProperty)
hasAccessorCandidates($propertyName): bool {
    $patterns = [
        fn($name) => 'has' . ucfirst($name),
        fn($name) => 'get' . ucfirst($name), 
        fn($name) => 'is' . ucfirst($name),
    ];
    
    // Requirements: public, no parameters, returns non-void
}
```

## Tool Call Builder System

### ToolCallBuilder - OpenAI Function Schema
```php
class ToolCallBuilder {
    private ReferenceQueue $references;      // Manages object references
    private SchemaFactory $schemaFactory;
    
    public function renderToolCall(array $jsonSchema, string $name, string $description): array;
}

// OpenAI function call format
renderToolCall() produces:
[
    'type' => 'function',
    'function' => [
        'name' => $name,
        'description' => $description,
        'parameters' => [
            ...$jsonSchema,                  // Spread JSON schema properties
            '$defs' => $this->references->definitions()  // If references exist
        ]
    ]
]
```

### Reference Queue Management
```php
class ReferenceQueue {
    private array $queue = [];
    private array $processed = [];
    
    public function queue(Reference $reference): void;
    public function dequeue(): ?Reference;
    public function hasQueued(): bool;
    public function definitions(): array;     // Recursive definition extraction
}

// Reference definition collection
definitions(): array {
    $definitions = [];
    while($this->references->hasQueued()) {
        $reference = $this->references->dequeue();
        $definitions[$reference->classShort] = $this->schemaFactory
            ->schema($reference->class)
            ->toJsonSchema();
    }
    return array_reverse($definitions);  // Preserve dependency order
}
```

## Advanced Patterns

### Type String Parsing
```php
class TypeString {
    public static function fromString(string $typeSpec): self;
    
    // Type classification
    public function isMixed(): bool;
    public function isScalar(): bool;
    public function isEnumObject(): bool;
    public function isObject(): bool;
    public function isCollection(): bool;      // Type[]
    public function isArray(): bool;           // array
    public function isUntypedObject(): bool;   // object without class
    public function isUntypedEnum(): bool;     // enum without class
    
    // Type extraction
    public function firstType(): string;
    public function itemType(): string;        // Collection item type
    public function className(): string;
}
```

### Compatibility Adapters
```php
// PropertyInfo V6/V7 compatibility
interface CanGetPropertyType {
    public function getPropertyTypeDetails(): TypeDetails;
    public function isPropertyNullable(): bool;
}

class PropertyInfoV6Adapter implements CanGetPropertyType {
    // Uses legacy PropertyInfo API
}

class PropertyInfoV7Adapter implements CanGetPropertyType {
    // Uses new TypeInterface API
}

// Automatic adapter selection
$useV7Adapter = class_exists("Symfony\Component\PropertyInfo\PropertyInfoExtractor")
    && method_exists($class, 'getType');
```

### Schema Caching and Maps
```php
class SchemaMap {
    public function has(string $typeName): bool;
    public function register(string $typeName, Schema $schema): void;
    public function get(string|object $anyType): Schema;
}

class PropertyMap {
    // Property-specific caching
}

// Type-based caching keys
$typeString = (string) $typeDetails;
if (!$this->schemaMap->has($typeString)) {
    $this->schemaMap->register($typeString, $this->makeSchema($type));
}
```

### Attribute Integration
```php
class AttributeUtils {
    public static function hasAttribute(ReflectionProperty $reflection, string $attributeClass): bool;
    public static function getValues(ReflectionProperty $reflection, string $attributeClass, string $property): array;
}

// Usage in PropertyInfo
$property->hasAttribute(InputField::class);
$values = $property->getAttributeValues(Description::class, 'value');
```

### Description Resolution
```php
class Descriptions {
    public static function forClass(string $class): string;
    public static function forProperty(string $class, string $property): string;
}

// DocString parsing integration
class DocstringUtils {
    // Extract descriptions from docblock comments
}
```

## Schema Validation and Type Safety

### TypeDetails Validation
```php
// Constructor validation ensures type consistency
validate($type, $class, $nestedType, $enumType, $enumValues): void {
    // Ensures:
    // - Objects have class names
    // - Enums have class names and backing types
    // - Collections have nested types
    // - Type/class combinations are valid
}
```

### JSON Type Conversion
```php
// TypeDetails to JSON Schema type mapping
toJsonType(): object {
    return match($this->type) {
        self::PHP_INT => 'integer',
        self::PHP_FLOAT => 'number', 
        self::PHP_STRING => 'string',
        self::PHP_BOOL => 'boolean',
        self::PHP_ARRAY => 'array',
        self::PHP_COLLECTION => 'array',
        self::PHP_OBJECT => 'object',
        self::PHP_ENUM => $this->enumType ?? 'string',
        default => 'string',
    };
}
```