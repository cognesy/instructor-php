<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanAcceptSchemaVisitor;
use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;
use Exception;

readonly class Schema implements CanAcceptSchemaVisitor
{
    public string $name;
    public string $description;
    public TypeDetails $typeDetails;

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
    ) {
        $this->typeDetails = $type;
        $this->name = $name;
        $this->description = $description;
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    public function name(): string {
        return $this->name;
    }

    public function description(): string {
        return $this->description;
    }

    public function typeDetails(): TypeDetails {
        return $this->typeDetails;
    }

    public function hasProperties(): bool {
        return false;
    }

    public function isScalar(): bool {
        return $this->typeDetails->isScalar();
    }

    public function isObject(): bool {
        return $this->typeDetails->isObject();
    }

    public function isEnum(): bool {
        return $this->typeDetails->isEnum();
    }

    public function isArray(): bool {
        return $this->typeDetails->isArray();
    }

    // MUTATORS ////////////////////////////////////////////////////////////////

    public function withName(string $name): self {
        return new self(
            type: $this->typeDetails,
            name: $name,
            description: $this->description,
        );
    }

    public function withDescription(string $description): self {
        return new self(
            type: $this->typeDetails,
            name: $this->name,
            description: $description,
        );
    }

    // OVERRIDES ///////////////////////////////////////////////////////////////

    /** @return string[] */
    public function getPropertyNames() : array {
        return [];
    }

    /** @return Schema[] */
    public function getPropertySchemas() : array {
        return [];
    }

    public function getPropertySchema(string $name) : Schema {
        throw new Exception('Property not found: ' . $name);
    }

    public function hasProperty(string $name) : bool {
        return false;
    }

    public function removeProperty(string $name): Schema {
        throw new Exception('Property can only be removed from ObjectSchema or ArrayShapeSchema: ' . $name);
    }

    // OTHER ///////////////////////////////////////////////////////////////////

    #[\Override]
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitSchema($this);
    }

    public function toJsonSchema() : array {
        return (new SchemaToJsonSchema)->toArray($this);
    }

    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'typeDetails' => $this->typeDetails->toArray(),
        ];
    }

    public function clone() : self {
        return new self(
            type: $this->typeDetails->clone(),
            name: $this->name,
            description: $this->description,
        );
    }

    // FACTORY METHODS //////////////////////////////////////////////////////////

    public static function undefined() : self {
        return new self(TypeDetails::undefined());
    }

    public static function fromTypeName(string $typeName) : Schema {
        return self::factory()->schema($typeName);
    }

    public static function string(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->string($name, $description);
    }

    public static function int(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->int($name, $description);
    }

    public static function float(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->float($name, $description);
    }

    public static function bool(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->bool($name, $description);
    }

    public static function array(string $name = '', string $description = ''): ArraySchema {
        return self::factory()->array($name, $description);
    }

    /**
     * @param class-string $class
     * @param array<string, Schema> $properties
     * @param array<string> $required
     */
    public static function object(string $class, string $name = '', string $description = '', array $properties = [], array $required = []): ObjectSchema {
        return self::factory()->object($class, $name, $description, $properties, $required);
    }

    /**
     * @param class-string $class
     */
    public static function enum(string $class, string $name = '', string $description = ''): EnumSchema {
        return self::factory()->enum($class, $name, $description);
    }

    public static function collection(string $nestedType, string $name = '', string $description = '', ?Schema $nestedItemSchema = null): CollectionSchema {
        return self::factory()->collection($nestedType, $name, $description, $nestedItemSchema);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    protected static function factory(): SchemaFactory {
        return new SchemaFactory(
            useObjectReferences: false,
            schemaConverter: new JsonSchemaToSchema(),
        );
    }
}
