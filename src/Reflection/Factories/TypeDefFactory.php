<?php

namespace Cognesy\Instructor\Reflection\Factories;

use Cognesy\Instructor\Attributes\ArrayOf;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\PhpDoc\DocstringUtils;
use Cognesy\Instructor\Reflection\TypeDefs\ArrayTypeDef;
use Cognesy\Instructor\Reflection\TypeDefs\EnumTypeDef;
use Cognesy\Instructor\Reflection\TypeDefs\ObjectTypeDef;
use Cognesy\Instructor\Reflection\TypeDefs\SimpleTypeDef;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDef;
use Cognesy\Instructor\Reflection\TypeDefs\TypeDefContext;
use Cognesy\Instructor\Reflection\TypeDefs\UndefinedTypeDef;
use Exception;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

class TypeDefFactory
{
    static public function fromReflectionProperty(ReflectionProperty $reflectionProperty): TypeDef
    {
        $type = $reflectionProperty->getType();
        if ($type === null) {
            return new UndefinedTypeDef();
        }
        $context = TypeDefContext::fromReflectionProperty($reflectionProperty);
        return (new TypeDefFactory)->getTypeDef($type->getName(), $context);
    }

    static public function fromReflectionParameter(ReflectionParameter $reflectionParameter): TypeDef
    {
        $type = $reflectionParameter->getType();
        if ($type === null) {
            return new UndefinedTypeDef();
        }
        $context = TypeDefContext::fromReflectionParameter($reflectionParameter);
        return (new TypeDefFactory)->getTypeDef($type->getName(), $context);
    }

    static public function fromTypeName(string $typeName) : TypeDef {
        return (new TypeDefFactory)->getTypeDef($typeName);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function getTypeDef(string $typeName, ?TypeDefContext $context = null): ?TypeDef
    {
        return match ($typeName) {
            'array' =>  $this->getArrayTypeFromContext($context),
            'string' => new SimpleTypeDef(PhpType::STRING),
            'int' => new SimpleTypeDef(PhpType::INTEGER),
            'float' => new SimpleTypeDef(PhpType::FLOAT),
            'bool' => new SimpleTypeDef(PhpType::BOOLEAN),
            'mixed' => new SimpleTypeDef(PhpType::MIXED),
            // 'iterable' => new SimpleTypeDef(),
            // 'callable' => return new SimpleTypeDef(),
            // 'void' => return new SimpleTypeDef(),
            // 'null' => return new SimpleTypeDef(),
            default => $this->getObjectOrEnumType($typeName),
        };
    }

    private function getObjectOrEnumType(string $typeName): ObjectTypeDef|EnumTypeDef|null
    {
        try {
            $class = new ReflectionClass($typeName);
        } catch (ReflectionException $e) {
            throw new Exception('Class not found: ' . $typeName . ' - ERROR: ' . $e->getMessage());
        }
        $className = $class->getName();
        if ($class->isEnum()) {
            $constants = (new ReflectionEnum($className))->getConstants();
            $values = [];
            foreach ($constants as $value) {
                $values[] = $value->value;
            }
            return new EnumTypeDef($className, $values);
        }
        return new ObjectTypeDef($class->getName());
    }

    private function getArrayTypeFromContext(?TypeDefContext $context): ArrayTypeDef
    {
        if (empty($context)) {
            throw new Exception('No context provided - nested arrays not supported');
        }

        $arrayTypeDef = $this->arrayFromAttributes($context);
        if ($arrayTypeDef !== null) {
            return $arrayTypeDef;
        }

        $arrayTypeDef = $this->arrayFromPhpDoc($context);
        if ($arrayTypeDef !== null) {
            return $arrayTypeDef;
        }

        throw new Exception('Array missing type definition: ' . $context->location);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function getArrayTypeDef(string $keyTypeName, string $valueTypeName) : ArrayTypeDef {
        $keyType = $this->makeArrayKeyType($keyTypeName);
        $valueType = $this->makeArrayValueType($valueTypeName);
        return new ArrayTypeDef(
            keyType: $keyType,
            valueType: $valueType,
        );
    }

    private function makeArrayKeyType(string $typeName) : PhpType {
        if (!empty($typeName)) {
            return PhpType::UNDEFINED;
        }
        if (!in_array($typeName, ['int', 'string'])) {
            throw new Exception('Unsupported array key type: ' . $typeName);
        }
        return PhpType::from($typeName);
    }

    private function makeArrayValueType(string $typeName) : TypeDef {
        if ($typeName === 'array') {
            throw new Exception('Array value type is not supported: ' . $typeName);
        }
        return $this->getTypeDef($typeName);
    }

    private function arrayFromAttributes(TypeDefContext $context) : ?ArrayTypeDef {
        $attribute = $context->attributes()->get(ArrayOf::class);
        if ($attribute === null) {
            return null;
        }
        $keyTypeName = $attribute->newInstance()->keyType;
        $valueTypeName = $attribute->newInstance()->valueType;
        return $this->getArrayTypeDef($keyTypeName, $valueTypeName);
    }

    private function arrayFromPhpDoc(TypeDefContext $context) : ?ArrayTypeDef {
        foreach ($context->tags()->all() as $tag) {
            $type = $tag->type;
            [$isResolved, $keyTypeName, $valueTypeName] = DocstringUtils::getPhpDocType($type);
            if ($isResolved) {
                return $this->getArrayTypeDef($keyTypeName, $valueTypeName);
            }
        }
        return null;
    }
}
