<?php
namespace Cognesy\Instructor\Schema\Visitors;

use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\Reference;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

class SchemaToArray implements CanVisitSchema
{
    private array $result = [];
    private $refCallback;
    private string $defsLabel = '$defs';

    public function toArray(Schema $schema, callable $refCallback = null): array {
        $this->refCallback = $refCallback;
        $schema->accept($this);
        return $this->result;
    }

    public function visitSchema(Schema $schema): void {
        $this->result = array_filter([
            'type' => $schema->type->type,
            'description' => $schema->description,
        ]);
    }

    public function visitArraySchema(ArraySchema $schema): void {
        $this->result = array_filter([
            'type' => 'array',
            'items' => (new SchemaToArray)->toArray($schema->nestedItemSchema, $this->refCallback),
            'description' => $schema->description,
        ]);
    }

    public function visitObjectSchema(ObjectSchema $schema): void {
        $propertyDefs = [];
        foreach ($schema->properties as $property) {
            $propertyDefs[$property->name] = (new SchemaToArray)->toArray($property, $this->refCallback);
        }
        $this->result = array_filter([
            'type' => 'object',
            'title' => $schema->name,
            'description' => $schema->description,
            'properties' => $propertyDefs,
            'required' => $schema->required,
            '$comment' => $schema->type->class,
        ]);
    }

    public function visitEnumSchema(EnumSchema $schema): void {
        $this->result = array_filter([
            'description' => $schema->description ?? '',
            'type' => $schema->type->enumType ?? 'string',
            'enum' => $schema->type->enumValues ?? [],
            '$comment' => $schema->type->class ?? '',
        ]);
    }

    public function visitScalarSchema(ScalarSchema $schema): void {
        $this->result = array_filter([
            'type' => $schema->type->jsonType(),
            'description' => $schema->description,
        ]);
    }

    public function visitObjectRefSchema(ObjectRefSchema $schema): void {
        $class = $this->className($schema->type->class);
        $id = "#/{$this->defsLabel}/{$class}";
        if ($this->refCallback) {
            ($this->refCallback)(new Reference(
                id: $id,
                class: $schema->type->class,
                classShort: $class
            ));
        }
        $this->result = array_filter([
            '$ref' => $id,
            'description' => $schema->description,
            '$comment' => $schema->type->class,
        ]);
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function className(string $fqcn) : string {
        $classSegments = explode('\\', $fqcn);
        return array_pop($classSegments);
    }
}