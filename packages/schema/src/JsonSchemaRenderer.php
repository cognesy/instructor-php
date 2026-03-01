<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Contracts\CanRenderJsonSchema;
use Cognesy\Schema\Data\ArraySchema;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectRefSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Utils\JsonSchema\JsonSchema;

class JsonSchemaRenderer implements CanRenderJsonSchema
{
    private string $defsLabel = '$defs';

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    public function toArray(Schema $schema, ?callable $refCallback = null) : array {
        return $this->renderArray($schema, $refCallback);
    }

    /**
     * @param callable(string): void|null $refCallback
     */
    #[\Override]
    public function render(Schema $schema, ?callable $onObjectRef = null) : JsonSchema {
        return JsonSchema::document($this->renderArray($schema, $onObjectRef));
    }

    /**
     * @param callable(string): void|null $onObjectRef
     * @return array<string, mixed>
     */
    private function renderArray(Schema $schema, ?callable $onObjectRef = null) : array {
        return match (true) {
            $schema instanceof ArraySchema => [
                'type' => 'array',
                'items' => ['anyOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'object']]],
                'description' => $schema->description,
            ],
            $schema instanceof CollectionSchema => array_filter([
                'type' => 'array',
                'items' => $this->renderArray($schema->nestedItemSchema, $onObjectRef),
                'description' => $schema->description,
            ]),
            $schema instanceof ObjectSchema => $this->renderObject($schema, $onObjectRef),
            $schema instanceof ArrayShapeSchema => $this->renderArrayShape($schema, $onObjectRef),
            $schema instanceof EnumSchema => array_filter([
                'type' => TypeInfo::enumBackingType($schema->type) ?? 'string',
                'description' => $schema->description,
                'enum' => $schema->enumValues ?? TypeInfo::enumValues($schema->type),
                'x-php-class' => TypeInfo::className($schema->type) ?? '',
            ]),
            $schema instanceof ScalarSchema => $this->renderScalar($schema),
            $schema instanceof ObjectRefSchema => $this->renderReference($schema, $onObjectRef),
            default => array_filter([
                'description' => $schema->description,
            ]),
        };
    }

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    private function renderObject(ObjectSchema $schema, ?callable $refCallback) : array {
        $className = TypeInfo::className($schema->type);
        if (TypeInfo::isDateTimeClass($schema->type)) {
            return array_filter([
                'type' => 'string',
                'x-title' => $schema->name,
                'description' => $schema->description,
                'x-php-class' => $className,
            ]);
        }

        $properties = [];
        foreach ($schema->properties as $propertyName => $property) {
            $properties[$propertyName] = $this->renderArray($property, $refCallback);
        }

        $result = array_filter([
            'type' => 'object',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'properties' => $properties,
            'required' => $schema->required,
            'x-php-class' => $className,
        ]);
        $result['additionalProperties'] = false;

        return $result;
    }

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    private function renderArrayShape(ArrayShapeSchema $schema, ?callable $refCallback) : array {
        $properties = [];
        foreach ($schema->properties as $propertyName => $property) {
            $properties[$propertyName] = $this->renderArray($property, $refCallback);
        }

        return array_filter([
            'type' => 'object',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'properties' => $properties,
            'required' => $schema->required,
        ]);
    }

    /** @return array<string, mixed> */
    private function renderScalar(ScalarSchema $schema) : array {
        $array = [
            'type' => TypeInfo::toJsonType($schema->type)->toString(),
            'description' => $schema->description,
        ];
        if ($schema->enumValues !== null && $schema->enumValues !== []) {
            $array['enum'] = $schema->enumValues;
        }

        return array_filter($array);
    }

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    private function renderReference(ObjectRefSchema $schema, ?callable $refCallback) : array {
        $className = TypeInfo::className($schema->type) ?? 'object';
        $classKey = str_replace('\\', '.', ltrim($className, '\\'));
        $id = "#/{$this->defsLabel}/{$classKey}";

        if ($refCallback !== null) {
            $refCallback($className);
        }

        return array_filter([
            '$ref' => $id,
            'description' => $schema->description,
            'x-php-class' => $className,
        ]);
    }
}
