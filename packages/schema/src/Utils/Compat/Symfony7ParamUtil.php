<?php

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Reflection\ParameterInfo;
use JetBrains\PhpStorm\Deprecated;
use ReflectionParameter;
use ReflectionType;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[Deprecated('Not needed, temporarily kept until all usages are removed')]
class Symfony7ParamUtil
{
    private PropertyInfoExtractor $extractor;

    public function getType(ParameterInfo $paramInfo): Type {
        $parameterTypes = $this->makeTypes($paramInfo->getReflection());
        if (!count($parameterTypes)) {
            throw new \Exception("No type found for parameter: {$paramInfo->getName()}");
        }
        if (count($parameterTypes) > 1) {
            throw new \Exception("Unsupported union type found for parameter: {$paramInfo->getName()}");
        }
        return $parameterTypes[0];
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////

    protected function makeTypes(ReflectionParameter $reflectionParameter): array {
        // For parameters, we need to extract type info differently
        // since PropertyInfoExtractor is designed for class properties
        if (!$reflectionParameter->hasType()) {
            return [Type::string()];
        }

        $reflectionType = $reflectionParameter->getType();

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
        return [Type::string()];
    }

    private function getBuiltinTypeName(ParameterInfo $paramInfo): string {
        try {
            $type = $this->getType($paramInfo);
            return match(true) {
                $type->isIdentifiedBy(TypeIdentifier::INT) => 'int',
                $type->isIdentifiedBy(TypeIdentifier::FLOAT) => 'float',
                $type->isIdentifiedBy(TypeIdentifier::STRING) => 'string',
                $type->isIdentifiedBy(TypeIdentifier::BOOL) => 'bool',
                $type->isIdentifiedBy(TypeIdentifier::ARRAY) => $this->getCollectionOrArrayType($type),
                $type->isIdentifiedBy(TypeIdentifier::OBJECT) => $type->getClassName() ?? 'object',
                $type->isIdentifiedBy(TypeIdentifier::CALLABLE) => 'callable',
                $type->isIdentifiedBy(TypeIdentifier::ITERABLE) => 'iterable',
                $type->isIdentifiedBy(TypeIdentifier::RESOURCE) => 'resource',
                $type->isIdentifiedBy(TypeIdentifier::NULL) => 'null',
                $type->isIdentifiedBy(TypeIdentifier::MIXED) => 'mixed',
                default => 'mixed',
            };
        } catch (\Exception $e) {
            return $this->getTypeName($paramInfo->getReflection());
        }
    }

    public function getTypeName(ReflectionParameter $paramReflection): string {
        if (!$paramReflection->hasType()) {
            return 'mixed';
        }

        $type = $paramReflection->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(fn(ReflectionType $t) => $t->getName(), $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = array_map(fn(ReflectionType $t) => $t->getName(), $type->getTypes());
            return implode('&', $types);
        }

        return 'mixed';
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $type->getCollectionValueType();
        $valueType = $valueType[0] ?? null;
        if (is_null($valueType)) {
            return 'array';
        }

        //$builtInType = $valueType->getBuiltinType();
        return match(true) {
            $valueType->isIdentifiedBy(TypeIdentifier::INT) => 'int[]',
            $valueType->isIdentifiedBy(TypeIdentifier::FLOAT) => 'float[]',
            $valueType->isIdentifiedBy(TypeIdentifier::STRING) => 'string[]',
            $valueType->isIdentifiedBy(TypeIdentifier::BOOL) => 'bool[]',
            $valueType->isIdentifiedBy(TypeIdentifier::ARRAY) => throw new \Exception("Nested arrays are not supported"),
            $valueType->isIdentifiedBy(TypeIdentifier::OBJECT) => ($valueType->getClassName() ?? 'object') . '[]',
            $valueType->isIdentifiedBy(TypeIdentifier::CALLABLE) => 'callable[]',
            $valueType->isIdentifiedBy(TypeIdentifier::ITERABLE) => 'iterable[]',
            $valueType->isIdentifiedBy(TypeIdentifier::RESOURCE) => 'resource[]',
            $valueType->isIdentifiedBy(TypeIdentifier::NULL) => 'null[]',
            $valueType->isIdentifiedBy(TypeIdentifier::MIXED) => 'mixed[]',
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

    private function convertReflectionTypeToPropertyInfoType(\ReflectionNamedType $reflectionType): Type {
        $typeName = $reflectionType->getName();
        $isNullable = $reflectionType->allowsNull();

        if ($reflectionType->isBuiltin()) {
            $builtinType = match($typeName) {
                'int' => Type::int(),
                'float' => Type::float(),
                'string' => Type::string(),
                'bool' => Type::bool(),
                'array' => Type::array(),
                'object' => Type::object($typeName),
                'callable' => Type::callable(),
                'iterable' => Type::iterable(),
                'resource' => Type::resource(),
                'null' => Type::null(),
                'mixed' => Type::mixed(), // fallback
                default => Type::string(), // fallback for unknown types
            };

            return $builtinType;
        }

        return Type::object($typeName);
    }
}