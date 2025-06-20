<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Reflection\ParameterInfo;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

#[Deprecated('Not needed, temporarily kept until all usages are removed')]
class Symfony6TypeUtil
{
    private PropertyInfoExtractor $extractor;

    public function getType(ParameterInfo $paramInfo): Type {
        $parameterTypes = $this->makeTypes($paramInfo);
        if (!count($parameterTypes)) {
            throw new \Exception("No type found for parameter: {$paramInfo->getName()}");
        }
        if (count($parameterTypes) > 1) {
            throw new \Exception("Unsupported union type found for parameter: {$paramInfo->getName()}");
        }
        return $parameterTypes[0];
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    protected function makeTypes(ParameterInfo $paramInfo): array {
        $reflection = $paramInfo->getReflection();

        // For parameters, we need to extract type info differently
        // since PropertyInfoExtractor is designed for class properties
        if (!$reflection->hasType()) {
            return [new Type(Type::BUILTIN_TYPE_STRING)];
        }

        $reflectionType = $reflection->getType();

        if ($reflectionType instanceof \ReflectionNamedType) {
            return [$this->convertReflectionTypeToPropertyInfoType($reflectionType)];
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($reflectionType->getTypes() as $type) {
                $types[] = $this->convertReflectionTypeToPropertyInfoType($type);
            }
            return $types;
        }

        // Default fallback
        return [new Type(Type::BUILTIN_TYPE_STRING)];
    }

    private function convertReflectionTypeToPropertyInfoType(\ReflectionNamedType $reflectionType): Type {
        $typeName = $reflectionType->getName();
        $isNullable = $reflectionType->allowsNull();

        if ($reflectionType->isBuiltin()) {
            $builtinType = match($typeName) {
                'int' => Type::BUILTIN_TYPE_INT,
                'float' => Type::BUILTIN_TYPE_FLOAT,
                'string' => Type::BUILTIN_TYPE_STRING,
                'bool' => Type::BUILTIN_TYPE_BOOL,
                'array' => Type::BUILTIN_TYPE_ARRAY,
                'object' => Type::BUILTIN_TYPE_OBJECT,
                'callable' => Type::BUILTIN_TYPE_CALLABLE,
                'iterable' => Type::BUILTIN_TYPE_ITERABLE,
                'resource' => Type::BUILTIN_TYPE_RESOURCE,
                'null' => Type::BUILTIN_TYPE_NULL,
                'mixed' => Type::BUILTIN_TYPE_STRING, // fallback
                default => Type::BUILTIN_TYPE_STRING,
            };

            return new Type($builtinType, $isNullable);
        }

        return new Type(Type::BUILTIN_TYPE_OBJECT, $isNullable, $typeName);
    }

    public function getBuiltinTypeName(ParameterInfo $parameterInfo): string {
        try {
            $type = $this->getType($parameterInfo);
            $builtInType = $type->getBuiltinType();
            return match($builtInType) {
                Type::BUILTIN_TYPE_INT => 'int',
                Type::BUILTIN_TYPE_FLOAT => 'float',
                Type::BUILTIN_TYPE_STRING => 'string',
                Type::BUILTIN_TYPE_BOOL => 'bool',
                Type::BUILTIN_TYPE_ARRAY => $this->getCollectionOrArrayType($type),
                Type::BUILTIN_TYPE_OBJECT => $type->getClassName() ?? 'object',
                Type::BUILTIN_TYPE_CALLABLE => 'callable',
                Type::BUILTIN_TYPE_ITERABLE => 'iterable',
                Type::BUILTIN_TYPE_RESOURCE => 'resource',
                Type::BUILTIN_TYPE_NULL => 'null',
                default => 'mixed',
            };
        } catch (\Exception $e) {
            // If we cannot determine the type, return 'mixed'
            return 'mixed';
        }
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $type->getCollectionValueTypes();
        $valueType = $valueType[0] ?? null;
        if (is_null($valueType)) {
            return 'array';
        }

        $builtInType = $valueType->getBuiltinType();
        return match($builtInType) {
            Type::BUILTIN_TYPE_INT => 'int[]',
            Type::BUILTIN_TYPE_FLOAT => 'float[]',
            Type::BUILTIN_TYPE_STRING => 'string[]',
            Type::BUILTIN_TYPE_BOOL => 'bool[]',
            Type::BUILTIN_TYPE_ARRAY => throw new \Exception("Nested arrays are not supported"),
            Type::BUILTIN_TYPE_OBJECT => ($valueType->getClassName() ?? 'object') . '[]',
            Type::BUILTIN_TYPE_CALLABLE => 'callable[]',
            Type::BUILTIN_TYPE_ITERABLE => 'iterable[]',
            Type::BUILTIN_TYPE_RESOURCE => 'resource[]',
            Type::BUILTIN_TYPE_NULL => 'null[]',
            default => 'array',
        };
    }

    private function extractor(): PropertyInfoExtractor {
        if (!isset($this->extractor)) {
            $this->extractor = $this->makeExtractor();
        }
        return $this->extractor;
    }

    protected function makeExtractor(): PropertyInfoExtractor {
        // initialize extractor instance
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );
    }
}