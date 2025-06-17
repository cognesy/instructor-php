<?php
namespace Cognesy\Schema\Visitors;

use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\Reference;
use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\MixedSchema;
use Cognesy\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\OptionSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use DateTime;
use DateTimeImmutable;

/**
 * Responsible for converting different schema types to their corresponding JSON schema representations.
 * Provides methods to visit and convert various schema objects.
 */
class SchemaToJsonSchema implements CanVisitSchema
{
    private array $result = [];
    private $refCallback;
    private string $defsLabel = '$defs';

    public function toArray(Schema $schema, ?callable $refCallback = null): array {
        $this->refCallback = $refCallback;
        $schema->accept($this);
        return $this->result;
    }

    public function visitSchema(Schema $schema): void {
        $this->result = array_filter([
            'type' => $schema->typeDetails->type,
            'description' => $schema->description,
        ]);
    }

    public function visitMixedSchema(MixedSchema $schema): void {
        $this->result = array_filter([
            //'type' => ['null', 'boolean', 'object', 'array', 'number', 'string'],
            'description' => $schema->description,
        ]);
    }

    public function visitArraySchema(ArraySchema $schema): void {
        $this->result = array_filter([
            'type' => 'array',
            'items' => ['anyOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean']]],
            'description' => $schema->description,
        ]);
    }

    public function visitCollectionSchema(CollectionSchema $schema): void {
        $this->result = array_filter([
            'type' => 'array',
            'items' => (new SchemaToJsonSchema)->toArray($schema->nestedItemSchema, $this->refCallback),
            'description' => $schema->description,
        ]);
    }

    public function visitObjectSchema(ObjectSchema $schema): void {
        // SPECIAL CASES: DateTime and DateTimeImmutable
        if (in_array($schema->typeDetails->class, [
            DateTime::class,
            DateTimeImmutable::class,
        ], false)) {
            $this->handleDateTimeSchema($schema);
            return;
        }

        // DEFAULT
        $propertyDefs = [];
        foreach ($schema->properties as $property) {
            $propertyDefs[$property->name] = (new SchemaToJsonSchema)->toArray($property, $this->refCallback);
        }
        $this->result = array_filter([
            'type' => 'object',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'properties' => $propertyDefs,
            'required' => $schema->required,
            'x-php-class' => $schema->typeDetails->class,
        ]);
        $this->result['additionalProperties'] = false;
    }

    public function handleDateTimeSchema(ObjectSchema $schema): void {
        $this->result = array_filter([
            'type' => 'string',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'x-php-class' => $schema->typeDetails->class,
        ]);
    }

    public function visitEnumSchema(EnumSchema $schema): void {
        $this->result = array_filter([
            'description' => $schema->description ?? '',
            'type' => $schema->typeDetails->enumType ?? 'string',
            'enum' => $schema->typeDetails->enumValues ?? [],
            'x-php-class' => $schema->typeDetails->class ?? '',
        ]);
    }

    public function visitOptionSchema(OptionSchema $param): void {
        $this->result = array_filter([
            'description' => $schema->description ?? '',
            'type' => 'string',
            'enum' => $schema->typeDetails->enumValues ?? [],
        ]);
    }

    public function visitScalarSchema(ScalarSchema $schema): void {
        $array = [
            'type' => $schema->typeDetails->jsonType(),
            'description' => $schema->description,
        ];
        if ($schema->typeDetails->enumValues) {
            $array['enum'] = $schema->typeDetails->enumValues;
        }
        $this->result = array_filter($array);
    }

    public function visitObjectRefSchema(ObjectRefSchema $schema): void {
        $class = $this->className($schema->typeDetails->class);
        $id = "#/{$this->defsLabel}/{$class}";
        if ($this->refCallback) {
            ($this->refCallback)(new Reference(
                id: $id,
                class: $schema->typeDetails->class,
                classShort: $class
            ));
        }
        $this->result = array_filter([
            '$ref' => $id,
            'description' => $schema->description,
            'x-php-class' => $schema->typeDetails->class,
        ]);
    }

    public function visitArrayShapeSchema(ArrayShapeSchema $schema): void {
        $propertyDefs = [];
        foreach ($schema->properties as $property) {
            $propertyDefs[$property->name] = (new SchemaToJsonSchema)->toArray($property, $this->refCallback);
        }
        $this->result = array_filter([
            'type' => 'object',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'properties' => $propertyDefs,
            'required' => $schema->required,
        ]);
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function className(string $fqcn) : string {
        $classSegments = explode('\\', $fqcn);
        return array_pop($classSegments);
    }
}