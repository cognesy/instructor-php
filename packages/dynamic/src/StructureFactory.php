<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Closure;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Cognesy\Schema\Utils\DocblockInfo;
use Cognesy\Utils\JsonSchema\JsonSchema;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class StructureFactory
{
    private TypeResolver $resolver;

    public function __construct(
        private readonly ?SchemaFactory $schemaFactory = null,
        ?TypeResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? TypeResolver::create();
    }

    /** @param callable(mixed...):mixed $callable */
    public function fromCallable(callable $callable, ?string $name = null, ?string $description = null) : Structure {
        return $this->fromReflection($this->reflectCallable($callable), $name, $description);
    }

    public function fromFunctionName(string $function, ?string $name = null, ?string $description = null) : Structure {
        return $this->fromReflection(new ReflectionFunction($function), $name, $description);
    }

    public function fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null) : Structure {
        return $this->fromReflection(new ReflectionMethod($class, $method), $name, $description);
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
        $builder = StructureBuilder::define($name, $description);

        foreach ($data as $field => $value) {
            if (!is_string($field)) {
                continue;
            }

            $schema = $this->schemaFactory()->fromType(TypeInfo::fromValue($value), $field, '');
            $builder = $builder->withField(Field::fromSchema($field, $schema, false));
        }

        return $builder->build();
    }

    public function fromString(string $name, string $typeString, string $description = '') : Structure {
        $builder = StructureBuilder::define($name, $description);
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

            $builder = $builder->withField(FieldFactory::fromTypeName($fieldName, $fieldType, $fieldDescription));
        }

        return $builder->build();
    }

    private function schemaFactory() : SchemaFactory {
        return $this->schemaFactory ?? SchemaFactory::default();
    }

    private function fromReflection(ReflectionFunctionAbstract $function, ?string $name, ?string $description) : Structure {
        $resolvedName = $name !== null && $name !== '' ? $name : $function->getShortName();
        $resolvedDescription = $description !== null && $description !== ''
            ? $description
            : DocblockInfo::summary($function->getDocComment() ?: '');

        $builder = StructureBuilder::define($resolvedName, $resolvedDescription);

        foreach ($function->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $field = FieldFactory::fromType(
                $parameterName,
                $this->parameterType($parameter),
                DocblockInfo::parameterDescription($function->getDocComment() ?: '', $parameterName),
            )->optional($parameter->isOptional());

            if ($parameter->isDefaultValueAvailable()) {
                $field = $field->withDefaultValue($parameter->getDefaultValue());
            }

            $builder = $builder->withField($field);
        }

        return $builder->build();
    }

    private function parameterType(ReflectionParameter $parameter) : Type {
        $resolved = $this->resolver->resolve($parameter);

        if (!$parameter->isVariadic()) {
            return $resolved;
        }

        if (TypeInfo::isCollection($resolved) || TypeInfo::isArray($resolved)) {
            return $resolved;
        }

        return Type::list($resolved);
    }

    /** @param callable(mixed...):mixed $callable */
    private function reflectCallable(callable $callable) : ReflectionFunctionAbstract {
        $closure = match (true) {
            $callable instanceof Closure => $callable,
            default => Closure::fromCallable($callable),
        };

        $reflection = new ReflectionFunction($closure);
        $scopeClass = $reflection->getClosureScopeClass()?->getName();
        $functionName = $reflection->getName();

        if ($scopeClass === null || str_contains($functionName, '{closure')) {
            return $reflection;
        }

        return new ReflectionMethod($scopeClass, $functionName);
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
