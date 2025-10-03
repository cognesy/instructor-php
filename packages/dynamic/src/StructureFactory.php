<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Closure;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\FunctionInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

class StructureFactory
{
    static public function fromArrayKeyValues(string $name, array $data, string $description = '') : Structure {
        $fields = self::makeArrayFields($data);
        return Structure::define($name, $fields, $description);
    }

    static private function makeArrayFields(array $data) : array {
        $fields = [];
        foreach ($data as $name => $value) {
            $typeDetails = TypeDetails::fromValue($value);
            $fields[] = FieldFactory::fromTypeDetails($name, $typeDetails, '')->optional();
        }
        return $fields;
    }

    static public function fromFunctionName(string $function, ?string $name = null, ?string $description = null) : Structure {
        return self::makeFromFunctionInfo(FunctionInfo::fromFunctionName($function), $name, $description);
    }

    static public function fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null) : Structure {
        return self::makeFromFunctionInfo(FunctionInfo::fromMethodName($class, $method), $name, $description);
    }

    /**
     * @param callable $callable
     * @phpstan-ignore-next-line
     */
    static public function fromCallable(callable $callable, ?string $name = null, ?string $description = null) : Structure {
        $closure = match(true) {
            $callable instanceof Closure => $callable,
            default => Closure::fromCallable($callable),
        };
        return self::makeFromFunctionInfo(FunctionInfo::fromClosure($closure), $name, $description);
    }

    static public function fromClass(
        string $class,
        ?string $name = null,
        ?string $description = null
    ) : Structure {
        $classInfo = ClassInfo::fromString($class);
        return self::fromClassInfo($classInfo, $name, $description);
    }

    static private function fromClassInfo(
        ClassInfo $classInfo,
        ?string $name = null,
        ?string $description = null
    ) : Structure {
        $className = $name ?? $classInfo->getShortName();
        $classDescription = $description ?? $classInfo->getClassDescription();
        $arguments = self::makePropertyFields($classInfo);
        return Structure::define($className, $arguments, $classDescription);
    }

    static public function fromJsonSchema(array $jsonSchema): Structure {
        $name = $jsonSchema['x-title'] ?? 'default_schema';
        $description = $jsonSchema['description'] ?? '';
        $schemaConverter = new JsonSchemaToSchema(
            defaultToolName: $name,
            defaultToolDescription: $description,
            defaultOutputClass: Structure::class,
        );
        $schema = $schemaConverter->fromJsonSchema($jsonSchema);
        return self::fromSchema($name, $schema, $description);
    }

    static public function fromSchema(string $name, Schema $schema, string $description = '') : Structure {
        $fields = self::makeSchemaFields($schema);
        $name = $name ?: $schema->name();
        $description = $description ?: $schema->description();
        return Structure::define($name, $fields, $description);
    }

    static public function fromString(string $name, string $typeString, string $description = '') : Structure {
        // Input format is:
        // 1) field1:string, field2:int, ...
        // 2) array{field1: string, field2: int, ...}
        // Additionally, you can add a description in () brackets:
        // field1:string (description), field2:int (description), ...
        $typeString = trim($typeString);
        if (str_starts_with($typeString, 'array{') && str_ends_with($typeString, '}')) {
            $typeString = substr($typeString, 6, -1);
        }
        $items = explode(',', $typeString);
        $fields = self::makeFieldsFromStrings($items);
        return Structure::define($name, $fields, $description);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    static private function makeFromFunctionInfo(
        FunctionInfo $functionInfo,
        ?string $name = null,
        ?string $description = null
    ) : Structure {
        $functionName = $name ?? $functionInfo->getShortName();
        $functionDescription = $description ?? $functionInfo->getDescription();
        $arguments = self::makeArgumentFields($functionInfo);
        return Structure::define($functionName, $arguments, $functionDescription);
    }

    static private function makeArgumentFields(FunctionInfo $functionInfo) : array {
        $arguments = [];
        foreach ($functionInfo->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $parameterDescription = $functionInfo->getParameterDescription($parameterName);
            $isOptional = $parameter->isOptional();
            $isVariadic = $parameter->isVariadic();
            $paramType = $parameter->getType()?->getName();
            $typeDetails = match($isVariadic) {
                true => TypeDetails::collection($paramType),
                default => TypeDetails::fromTypeName($paramType),
            };
            $defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            $arguments[] = FieldFactory::fromTypeDetails($parameterName, $typeDetails, $parameterDescription)
                ->optional($isOptional)
                ->withDefaultValue($defaultValue);
        }
        return $arguments;
    }

    /**
     * @return Field[]
     */
    static private function makePropertyFields(ClassInfo $classInfo) : array {
        $arguments = [];
        foreach ($classInfo->getProperties() as $propertyName => $propertyInfo) {
            switch (true) {
                case $propertyInfo->isStatic():
                // GETTERS SUPPORT
                //case !$propertyInfo->isPublic():
                case $propertyInfo->isReadOnly():
                case $propertyInfo->hasAttribute(Ignore::class):
                    continue 2;
            }
            $arguments[] = FieldFactory::fromTypeDetails(
                $propertyName,
                $propertyInfo->getTypeDetails(),
                $propertyInfo->getDescription()
            )->optional($propertyInfo->isNullable());
        }
        return $arguments;
    }

    static private function makeSchemaFields(Schema $schema) : array {
        $fields = [];
        $required = ($schema instanceof ObjectSchema) ? $schema->required : [];
        foreach ($schema->getPropertySchemas() as $propertyName => $propertySchema) {
            $typeDetails = $propertySchema->typeDetails();
            $field = FieldFactory::fromTypeDetails($propertyName, $typeDetails, $propertySchema->description());
            $isRequired = in_array($propertyName, $required, true);
            $fields[] = match (true) {
                $isRequired => $field->required(),
                default => $field->optional(),
            };
        }
        return $fields;
    }

    /** @param string[] $data */
    /** @return Field[] */
    static private function makeFieldsFromStrings(array $items) : array {
        $fields = [];
        foreach ($items as $item) {
            $description = self::extractDescription($item);
            $item = self::removeDescription($item);
            [$name, $typeName] = self::parseStringParam($item);
            $fields[] = FieldFactory::fromTypeName($name, $typeName, $description);
        }
        return $fields;
    }

    static private function extractDescription(string $item) : string {
        // possible formats: field1:string (description), field2:int (description), ...
        $item = trim($item);
        $parts = explode('(', $item);
        $description = '';
        if (count($parts) > 1) {
            $description = substr($parts[1], 0, -1);
        }
        return $description;
    }

    static private function removeDescription(string $item) : string {
        $parts = explode('(', $item);
        return $parts[0];
    }

    /** @return array{string, string} */
    static private function parseStringParam(string $paramString) : array {
        $paramString = str_replace(' ', '', $paramString);
        $parts = explode(':', $paramString);
        if (count($parts) > 2) {
            throw new \InvalidArgumentException('Invalid parameter string');
        }
        return [
            trim($parts[0]),
            trim($parts[1] ?? 'string')
        ];
    }
}
