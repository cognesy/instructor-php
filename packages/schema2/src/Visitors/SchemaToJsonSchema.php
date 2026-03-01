<?php declare(strict_types=1);

namespace Cognesy\Schema\Visitors;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use DateTime;
use DateTimeImmutable;

class SchemaToJsonSchema
{
    private string $defsLabel = '$defs';

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    public function toArray(Schema $schema, ?callable $refCallback = null) : array {
        return $this->render($schema, $refCallback);
    }

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    private function render(Schema $schema, ?callable $refCallback) : array {
        return match (true) {
            $schema instanceof ArraySchema => [
                'type' => 'array',
                'items' => ['anyOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'object']]],
                'description' => $schema->description,
            ],
            $schema instanceof CollectionSchema => array_filter([
                'type' => 'array',
                'items' => $this->render($schema->nestedItemSchema, $refCallback),
                'description' => $schema->description,
            ]),
            $schema instanceof ObjectSchema => $this->renderObject($schema, $refCallback),
            $schema instanceof ArrayShapeSchema => $this->renderArrayShape($schema, $refCallback),
            $schema instanceof EnumSchema => array_filter([
                'type' => $schema->typeDetails->enumType ?? 'string',
                'description' => $schema->description,
                'enum' => $schema->typeDetails->enumValues ?? [],
                'x-php-class' => $schema->typeDetails->class ?? '',
            ]),
            $schema instanceof ScalarSchema => $this->renderScalar($schema),
            $schema instanceof ObjectRefSchema => $this->renderReference($schema, $refCallback),
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
        if (in_array($schema->typeDetails->class, [DateTime::class, DateTimeImmutable::class], true)) {
            return array_filter([
                'type' => 'string',
                'x-title' => $schema->name,
                'description' => $schema->description,
                'x-php-class' => $schema->typeDetails->class,
            ]);
        }

        $properties = [];
        foreach ($schema->properties as $property) {
            $properties[$property->name] = $this->render($property, $refCallback);
        }

        $result = array_filter([
            'type' => 'object',
            'x-title' => $schema->name,
            'description' => $schema->description,
            'properties' => $properties,
            'required' => $schema->required,
            'x-php-class' => $schema->typeDetails->class,
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
        foreach ($schema->properties as $property) {
            $properties[$property->name] = $this->render($property, $refCallback);
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
            'type' => $schema->typeDetails->toJsonType()->toString(),
            'description' => $schema->description,
        ];
        if ($schema->typeDetails->enumValues) {
            $array['enum'] = $schema->typeDetails->enumValues;
        }

        return array_filter($array);
    }

    /**
     * @param callable(string): void|null $refCallback
     * @return array<string, mixed>
     */
    private function renderReference(ObjectRefSchema $schema, ?callable $refCallback) : array {
        $className = $schema->typeDetails->class ?? 'object';
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
