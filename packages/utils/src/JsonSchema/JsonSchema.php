<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema;

use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Exception;
use RuntimeException;

/**
 * JSON Schema definition API - helps to specify JSON Schema
 * using static factory and fluent methods.
 *
 * EXAMPLE #1:
 *
 *    $schema = JsonSchema::object(
 *        name: 'User',
 *        description: 'User object',
 *        properties: [
 *            JsonSchema::string(name: 'id'),
 *            JsonSchema::string(name: 'name'),
 *        ],
 *        required: ['id', 'name'],
 *    );
 *
 * EXAMPLE #2:
 *
 *    $schema2 = JsonSchema::array('list')
 *        ->withItemSchema(JsonSchema::string())
 *        ->withRequired(['id', 'name']);
 *
 */
class JsonSchema implements CanProvideJsonSchema
{
    use Traits\HandlesAccess;
    use Traits\HandlesMutation;
    use Traits\HandlesTypeFactory;
    use Traits\HandlesTransformation;

    protected JsonSchemaType $type;
    protected string $name;
    protected ?bool $nullable = null;
    /** @var JsonSchema[]|null */
    protected ?array $properties = null;
    /** @var array<string>|null */
    protected ?array $requiredProperties = null;
    protected ?JsonSchema $itemSchema = null;
    /** @var array<string>|null */
    protected ?array $enumValues = null;
    protected ?bool $additionalProperties = null;
    protected ?string $description = null;
    protected ?string $title = null;
    protected ?string $ref = null;
    /** @var array<string, JsonSchema>|null */
    protected ?array $defs = null;
    /** @var array<string, mixed> */
    protected array $meta = [];
    /** @var array<string, mixed>|null */
    protected ?array $rawSchema = null;

    private function __construct(
        JsonSchemaType $type,
        string      $name = '',
        ?bool       $nullable = null,
        ?array      $properties = null,
        ?array      $requiredProperties = null,
        ?JsonSchema $itemSchema = null,
        ?array      $enumValues = null,
        ?bool       $additionalProperties = null,
        ?string     $description = null,
        ?string     $title = null,
        ?string     $ref = null,
        ?array      $defs = null,
        array       $meta = [],
        ?array      $rawSchema = null,
        // public array $oneOf = [],
        // public array $anyOf = [],
        // public array $allOf = [],
        // public array $not = [],
        // public array $dependencies = [],
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->nullable = $nullable;
        $this->requiredProperties = $requiredProperties;
        $this->itemSchema = $itemSchema;
        $this->enumValues = $enumValues;
        $this->additionalProperties = $additionalProperties;
        $this->description = $description;
        $this->title = $title;
        $this->ref = $ref;
        $this->defs = self::toKeyedDefs($defs);
        $this->meta = $meta;
        $this->rawSchema = $rawSchema;

        if ($this->enumValues !== null) {
            // validate enum values are strings
            foreach ($this->enumValues as $value) {
                if (!is_string($value)) {
                    throw new RuntimeException('Invalid JSON type: invalid in: ' . $this->name . ' - enum values must be strings');
                }
            }
        }

        $this->properties = self::toKeyedProperties($properties);
    }

    // MAIN ////////////////////////////////////////////////////////////////////

    public static function fromArray(
        array $data,
        ?string $name = null,
        ?bool $required = null
    ) : self {
        if (empty($data)) {
            return new self(
                type: JsonSchemaType::any(),
                name: $name ?? '',
                nullable: true,
            );
        }

        $type = JsonSchemaType::fromJsonData($data);
        $nullable = self::resolveNullable($data, $required);

        $properties = [];
        if (isset($data['properties'])) {
            foreach ($data['properties'] as $propertyName => $propertyData) {
                $isRequired = in_array($propertyName, $data['required'] ?? [], true);
                $properties[$propertyName] = self::fromArray($propertyData, $propertyName, $isRequired);
            }
            $properties = array_filter($properties);
        }

        $itemSchema = match(true) {
            isset($data['items']) => self::fromArray($data['items']),
            default => null,
        };

        $defs = [];
        if (isset($data['$defs']) && is_array($data['$defs'])) {
            foreach ($data['$defs'] as $defName => $defData) {
                if (!is_string($defName) || !is_array($defData)) {
                    continue;
                }

                $defs[$defName] = self::fromArray($defData, $defName);
            }
        }

        return new self(
            type: $type,
            name: $name ?? $data['name'] ?? $data['x-name'] ?? '',
            nullable: $nullable,
            properties: $properties,
            requiredProperties: $data['required'] ?? null,
            itemSchema: $itemSchema,
            enumValues: $data['enum'] ?? null,
            additionalProperties: $data['additionalProperties'] ?? null,
            description: $data['description'] ?? '',
            title: $data['title'] ?? '',
            ref: is_string($data['$ref'] ?? null) ? $data['$ref'] : null,
            defs: $defs,
            meta: self::extractMetaFields(
                data: $data,
                excludedFields: ['type', 'nullable', 'properties', 'required', 'items', 'enum', 'additionalProperties', 'title', 'description', '$ref', '$defs']
            ),
        );
    }

    /** @param array<string, mixed> $document */
    public static function document(array $document) : self {
        $schema = self::fromArray($document);
        $schema->rawSchema = $document;
        return $schema;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    private static function extractMetaFields(array $data, array $excludedFields) : array {
        $meta = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $excludedFields, true)) {
                continue;
            }
            // if key starts with x- add it to meta
            if (str_starts_with($key, 'x-')) {
                $actualKey = substr($key, 2);
                $meta[$actualKey] = $value;
            }
        }
        return $meta;
    }

    private static function resolveNullable(array $data, ?bool $required) : ?bool {
        if (array_key_exists('nullable', $data)) {
            return is_bool($data['nullable']) ? $data['nullable'] : null;
        }

        if (self::hasNullType($data)) {
            return true;
        }

        return match(true) {
            $required === null => null,
            default => !$required,
        };
    }

    private static function hasNullType(array $data) : bool {
        $type = $data['type'] ?? null;
        if (is_array($type) && in_array('null', $type, true)) {
            return true;
        }

        if (!isset($data['anyOf']) || !is_array($data['anyOf'])) {
            return false;
        }

        foreach ($data['anyOf'] as $branch) {
            if (!is_array($branch)) {
                continue;
            }

            $branchType = $branch['type'] ?? null;
            if ($branchType === 'null') {
                return true;
            }
            if (is_array($branchType) && in_array('null', $branchType, true)) {
                return true;
            }
        }

        return false;
    }

    private static function toKeyedProperties(?array $properties) : array {
        if (empty($properties)) {
            return [];
        }

        // supported property values:
        //  a) string-key => array
        //  b) string-key => object
        //  c) int => array
        //  d) int => object
        // and turn them into keyed array of JsonSchema objects

        $keyedProperties = [];
        foreach ($properties as $key => $property) {
            $index = match(true) {
                is_int($key) && is_array($property) => $property['name'] ?? $property['x-name'] ?? null,
                is_int($key) && is_object($property) => (
                    (property_exists($property, 'name') ? $property->name : null)
                    ?? (property_exists($property, 'meta') && isset($property->meta['name']) ? $property->meta['name'] : null)
                    ?? (property_exists($property, 'meta') && isset($property->meta['x-name']) ? $property->meta['x-name'] : null)
                ),
                is_string($key) => $key,
                default => null,
            };

            if ($index === null) {
                throw new Exception('Missing property name: ' . print_r($property, true));
            }

            $keyedProperties[$index] = match(true) {
                $property instanceof self => $property->withName($index),
                is_array($property) => self::fromArray($property)->withName($index),
                default => throw new Exception('Invalid property: ' . print_r($property, true)),
            };
        }
        return $keyedProperties;
    }

    /** @param array<string, mixed|JsonSchema>|null $defs */
    private static function toKeyedDefs(?array $defs) : array {
        if (empty($defs)) {
            return [];
        }

        $result = [];
        foreach ($defs as $key => $definition) {
            if (!is_string($key)) {
                continue;
            }

            $result[$key] = match (true) {
                $definition instanceof self => $definition,
                is_array($definition) => self::fromArray($definition, $key),
                default => throw new Exception('Invalid definition: ' . print_r($definition, true)),
            };
        }

        return $result;
    }
}
