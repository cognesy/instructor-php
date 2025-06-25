<?php

namespace Cognesy\Schema\Factories\Traits\SchemaFactory;

use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\PropertyInfo;

trait HandlesClassInfo
{
    public function fromClassInfo(ClassInfo $classInfo) : ObjectSchema {
        return new ObjectSchema(
            TypeDetails::fromTypeName($classInfo->getClass()),
            $classInfo->getClass(),
            $classInfo->getClassDescription(),
            $this->getPropertySchemas($classInfo),
            $classInfo->getRequiredProperties(),
        );
    }

    public function fromPropertyInfo(PropertyInfo $propertyInfo) : Schema {
        return $this->makePropertySchema(
            $propertyInfo->getTypeDetails(),
            $propertyInfo->getName(),
            $propertyInfo->getDescription()
        );
    }

    /**
     * Gets all the property schemas of a class
     *
     * @return Schema[]
     */
    protected function getPropertySchemas(ClassInfo $classInfo) : array {
        $properties = $classInfo->getProperties();
        $propertySchemas = [];
        foreach ($properties as $propertyName => $propertyInfo) {
            if (!$propertyInfo->isDeserializable()) {
                continue;
            }
            $propertySchemas[$propertyName] = $this->fromPropertyMap($propertyInfo);
        }
        return $propertySchemas;
    }

    /**
     * Finds or creates the schema for a property of a class
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