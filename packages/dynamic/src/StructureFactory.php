<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Schema\CallableSchemaFactory;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Cognesy\Utils\JsonSchema\JsonSchema;

final class StructureFactory
{
    public function __construct(
        private readonly ?SchemaFactory $schemaFactory = null,
        private readonly ?CallableSchemaFactory $callableSchemaFactory = null,
    ) {}

    /** @param callable(mixed...):mixed $callable */
    public function fromCallable(callable $callable, ?string $name = null, ?string $description = null) : Structure {
        $schema = $this->callableSchemaFactory()->fromCallable($callable, $name, $description);
        return $this->fromSchema($schema->name(), $schema, $schema->description());
    }

    public function fromFunctionName(string $function, ?string $name = null, ?string $description = null) : Structure {
        $schema = $this->callableSchemaFactory()->fromFunctionName($function, $name, $description);
        return $this->fromSchema($schema->name(), $schema, $schema->description());
    }

    public function fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null) : Structure {
        $schema = $this->callableSchemaFactory()->fromMethodName($class, $method, $name, $description);
        return $this->fromSchema($schema->name(), $schema, $schema->description());
    }

    public function fromClass(string $class, ?string $name = null, ?string $description = null) : Structure {
        $schema = $this->schemaFactory()->schema($class);
        return $this->fromSchema($name ?? $schema->name(), $schema, $description ?? $schema->description());
    }

    public function fromSchema(string $name, Schema $schema, string $description = '') : Structure {
        $namedSchema = SchemaFactory::withMetadata(
            $schema,
            name: $name !== '' ? $name : $schema->name(),
            description: $description !== '' ? $description : $schema->description(),
        );

        return Structure::fromSchema($namedSchema);
    }

    /** @param array<string,mixed> $jsonSchema */
    public function fromJsonSchema(array $jsonSchema) : Structure {
        $name = is_string($jsonSchema['x-title'] ?? null) ? $jsonSchema['x-title'] : 'schema';
        $description = is_string($jsonSchema['description'] ?? null) ? $jsonSchema['description'] : '';
        $schema = $this->schemaFactory()->schemaParser()->parse(JsonSchema::fromArray($jsonSchema));
        return Structure::fromSchema(SchemaFactory::withMetadata($schema, $name, $description));
    }

    /** @param array<string,mixed> $data */
    public function fromArrayKeyValues(string $name, array $data, string $description = '') : Structure {
        $builder = SchemaBuilder::define($name, $description);

        foreach ($data as $field => $value) {
            if (!is_string($field)) {
                continue;
            }

            $schema = $this->schemaFactory()->fromType(TypeInfo::fromValue($value), $field, '');
            $builder = $builder->withProperty($field, $schema, required: false);
        }

        return Structure::fromSchema($builder->schema());
    }

    public function fromString(string $name, string $typeString, string $description = '') : Structure {
        $builder = SchemaBuilder::define($name, $description);
        $trimmed = trim($typeString);
        $source = str_starts_with($trimmed, 'array{') && str_ends_with($trimmed, '}')
            ? substr($trimmed, 6, -1)
            : $trimmed;

        foreach (explode(',', $source) as $part) {
            $normalized = trim($part);
            if ($normalized === '') {
                continue;
            }

            [$fieldName, $fieldType, $fieldDescription] = $this->parseStringField($normalized);
            if ($fieldName === '') {
                continue;
            }

            $schema = $this->schemaFactory()->fromType(
                type: TypeInfo::fromTypeName($fieldType),
                name: $fieldName,
                description: $fieldDescription,
            );
            $builder = $builder->withProperty($fieldName, $schema);
        }

        return Structure::fromSchema($builder->schema());
    }

    private function schemaFactory() : SchemaFactory {
        return $this->schemaFactory ?? SchemaFactory::default();
    }

    private function callableSchemaFactory() : CallableSchemaFactory {
        return $this->callableSchemaFactory ?? new CallableSchemaFactory(schemaFactory: $this->schemaFactory());
    }

    /** @return array{string, string, string} */
    private function parseStringField(string $definition) : array {
        $description = '';
        if (preg_match('/\((.*?)\)\s*$/', $definition, $matches) === 1) {
            $description = trim($matches[1]);
            $definition = trim((string) preg_replace('/\s*\(.*?\)\s*$/', '', $definition));
        }

        $chunks = explode(':', str_replace(' ', '', $definition));

        return [
            trim($chunks[0] ?? ''),
            trim($chunks[1] ?? 'string'),
            $description,
        ];
    }

}
