<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use DateTime;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/** Lightweight field definition used by Structure and StructureBuilder. */
final class Field
{
    /** @var (callable(mixed):(bool|ValidationResult))|null */
    private $validator = null;

    /** @param (callable(mixed):(bool|ValidationResult))|null $validationRule */
    public function __construct(
        private readonly string $name,
        private readonly Schema $schema,
        private bool $required = true,
        private mixed $defaultValue = null,
        private bool $hasExplicitDefaultValue = false,
        private string $validatorMessage = 'Invalid field value',
        ?callable $validationRule = null,
    ) {
        $this->validator = $validationRule;
    }

    public static function fromSchema(string $name, Schema $schema, bool $required = true) : self {
        return new self($name, $schema, $required);
    }

    public static function string(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->string($name, $description));
    }

    public static function int(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->int($name, $description));
    }

    public static function float(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->float($name, $description));
    }

    public static function bool(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->bool($name, $description));
    }

    public static function array(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->array($name, $description));
    }

    public static function datetime(string $name, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->fromType(Type::object(DateTime::class), $name, $description));
    }

    /** @param class-string $class */
    public static function object(string $name, string $class, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->fromType(Type::object($class), $name, $description));
    }

    /** @param class-string $enumClass */
    public static function enum(string $name, string $enumClass, string $description = '') : self {
        return self::fromSchema($name, SchemaFactory::default()->enum($enumClass, $name, $description));
    }

    /** @param array<string|int> $values */
    public static function option(string $name, array $values, string $description = '') : self {
        $schema = SchemaFactory::default()->propertySchema(Type::string(), $name, $description, $values);
        return self::fromSchema($name, $schema);
    }

    /** @param array<Field>|callable(StructureBuilder):(array<Field>|StructureBuilder) $fields */
    public static function structure(string $name, array|callable $fields, string $description = '') : self {
        $structure = Structure::define($name, $fields, $description);
        $schema = SchemaFactory::withMetadata(
            schema: $structure->schema(),
            name: $name,
            description: $description !== '' ? $description : $structure->description(),
        );
        return self::fromSchema($name, $schema);
    }

    public static function collection(string $name, string|Type|Structure $itemType, string $description = '') : self {
        $schemaFactory = SchemaFactory::default();

        $schema = match (true) {
            is_string($itemType) => $schemaFactory->collection($itemType, $name, $description),
            $itemType instanceof Type => new CollectionSchema(
                type: Type::list(TypeInfo::normalize($itemType)),
                name: $name,
                description: $description,
                nestedItemSchema: $schemaFactory->fromType(TypeInfo::normalize($itemType), 'item', ''),
            ),
            $itemType instanceof Structure => $schemaFactory->collection(Structure::class, $name, $description, $itemType->schema()),
            default => throw new \InvalidArgumentException('Invalid collection item type: ' . get_debug_type($itemType)),
        };

        return self::fromSchema($name, $schema);
    }

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->schema->description();
    }

    public function schema() : Schema {
        return $this->schema;
    }

    public function type() : Type {
        return $this->schema()->type();
    }

    public function className() : ?string {
        return TypeInfo::className($this->type());
    }

    public function required() : self {
        return $this->copyWith(
            required: true,
            defaultValue: $this->defaultValue,
            hasExplicitDefaultValue: $this->hasExplicitDefaultValue,
            validator: $this->validator,
            validatorMessage: $this->validatorMessage,
        );
    }

    public function optional(bool $optional = true) : self {
        return $this->copyWith(
            required: !$optional,
            defaultValue: $this->defaultValue,
            hasExplicitDefaultValue: $this->hasExplicitDefaultValue,
            validator: $this->validator,
            validatorMessage: $this->validatorMessage,
        );
    }

    public function isRequired() : bool {
        return $this->required;
    }

    public function isOptional() : bool {
        return !$this->required;
    }

    public function withDefaultValue(mixed $value) : self {
        return $this->copyWith(
            required: $this->required,
            defaultValue: $value,
            hasExplicitDefaultValue: true,
            validator: $this->validator,
            validatorMessage: $this->validatorMessage,
        );
    }

    public function hasDefaultValue() : bool {
        return $this->hasExplicitDefaultValue;
    }

    public function defaultValue() : mixed {
        return $this->defaultValue;
    }

    /** @param callable(mixed): (bool|ValidationResult) $validator */
    public function validIf(callable $validator, string $error = 'Invalid field value') : self {
        return $this->copyWith(
            required: $this->required,
            defaultValue: $this->defaultValue,
            hasExplicitDefaultValue: $this->hasExplicitDefaultValue,
            validator: $validator,
            validatorMessage: $error,
        );
    }

    public function validate(mixed $value = null) : ValidationResult {
        if ($this->validator === null) {
            return ValidationResult::valid();
        }

        $candidate = func_num_args() === 0 ? $this->defaultValue : $value;
        $result = ($this->validator)($candidate);
        if ($result instanceof ValidationResult) {
            return $result;
        }

        if ($result === true) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid(new ValidationError($this->name, $candidate, $this->validatorMessage));
    }

    /**
     * Transitional shape used by legacy callsites.
     *
     * @return object{class:?string,type:string,isCollection:bool,nestedType:?object}
     */
    public function typeDetails() : object {
        $type = $this->type();
        $isCollection = TypeInfo::isCollection($type);
        $nestedType = null;

        if ($isCollection) {
            $nested = TypeInfo::collectionValueType($type);
            if ($nested !== null) {
                $nestedType = (object) [
                    'class' => TypeInfo::className($nested),
                    'type' => self::typeName($nested),
                ];
            }
        }

        return (object) [
            'class' => $this->className(),
            'type' => $isCollection ? 'collection' : self::typeName($type),
            'isCollection' => $isCollection,
            'nestedType' => $nestedType,
        ];
    }

    private static function typeName(Type $type) : string {
        return match (true) {
            TypeInfo::isEnum($type) => 'enum',
            TypeInfo::isObject($type) => 'object',
            TypeInfo::isArray($type) => 'array',
            $type->isIdentifiedBy(TypeIdentifier::INT) => 'int',
            $type->isIdentifiedBy(TypeIdentifier::FLOAT) => 'float',
            TypeInfo::isBool($type) => 'bool',
            $type->isIdentifiedBy(TypeIdentifier::STRING) => 'string',
            default => (string) $type,
        };
    }

    /** @param (callable(mixed):(bool|ValidationResult))|null $validator */
    private function copyWith(
        bool $required,
        mixed $defaultValue,
        bool $hasExplicitDefaultValue,
        ?callable $validator,
        string $validatorMessage,
    ) : self {
        return new self(
            name: $this->name,
            schema: $this->schema,
            required: $required,
            defaultValue: $defaultValue,
            hasExplicitDefaultValue: $hasExplicitDefaultValue,
            validatorMessage: $validatorMessage,
            validationRule: $validator,
        );
    }
}
