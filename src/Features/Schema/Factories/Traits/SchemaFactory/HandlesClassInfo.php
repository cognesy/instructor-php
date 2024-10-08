<?php

namespace Cognesy\Instructor\Features\Schema\Factories\Traits\SchemaFactory;

use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Features\Schema\Utils\PropertyInfo;
use Exception;

trait HandlesClassInfo
{
    /**
     * @param ClassInfo $classInfo
     * @return ObjectSchema
     * @throws Exception
     */
    public function fromClassInfo(ClassInfo $classInfo) : ObjectSchema {
        return new ObjectSchema(
            $this->typeDetailsFactory->fromTypeName($classInfo->getClass()),
            $classInfo->getClass(),
            $classInfo->getClassDescription(),
            $this->getPropertySchemas($classInfo),
            $classInfo->getRequiredProperties(),
        );
    }

    /**
     * @param PropertyInfo $propertyInfo
     * @return Schema
     */
    public function fromPropertyInfo(PropertyInfo $propertyInfo) : Schema {
        return $this->makePropertySchema(
            $this->typeDetailsFactory->fromPropertyInfo($propertyInfo->getType()),
            $propertyInfo->getName(),
            $propertyInfo->getDescription()
        );
    }

    /**
     * Gets all the property schemas of a class
     * @param ClassInfo $classInfo
     * @return Schema[]
     */
    protected function getPropertySchemas(ClassInfo $classInfo) : array {
        $properties = $classInfo->getProperties();
        $propertySchemas = [];
        foreach ($properties as $propertyName => $propertyInfo) {
            if (!$propertyInfo->isPublic()) {
                continue;
            }
            $propertySchemas[$propertyName] = $this->fromPropertyMap($propertyInfo);
        }
        return $propertySchemas;
    }

    /**
     * Finds or creates the schema for a property of a class
     *
     * @param string $class
     * @param string $property
     */
    protected function fromPropertyMap(PropertyInfo $propertyInfo) : Schema {
        // if this property is not yet registered - generate it, register and return
        $class = $propertyInfo->getClass();
        $property = $propertyInfo->getName();
        if (!$this->propertyMap->has($class, $property)) {
            $this->propertyMap->register(
                class: $class,
                property: $property,
                schema: $this->fromPropertyInfo($propertyInfo)
            );
        }
        return $this->propertyMap->get($class, $property);
    }
}