<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait ProvidesSchema
{
    public function toSchema(): Schema {
        $classInfo = new ClassInfo(static::class);
        return $this->makeSchema($classInfo, [
            fn($property) => $property->hasAttribute(InputField::class) || $property->hasAttribute(OutputField::class)
        ]);
    }

    public function toInputSchema(): Schema {
        $classInfo = new ClassInfo(static::class);
        return $this->makeSchema($classInfo, [fn($property) => $property->hasAttribute(InputField::class)]);
    }

    public function toOutputSchema(): Schema {
        $classInfo = new ClassInfo(static::class);
        return $this->makeSchema($classInfo, [fn($property) => $property->hasAttribute(OutputField::class)]);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function makeSchema(ClassInfo $classInfo, array $filters): Schema {
        $properties = $this->getProperties($classInfo, $filters);
        $propertySchemas = [];
        foreach ($properties as $property) {
            $propertySchemas[$property->getName()] = (new SchemaFactory)->fromPropertyInfo($property);
        }

        $required = array_keys(
            array_filter($properties, fn($property) => !$property->isNullable())
        );
        $typeDetails = (new TypeDetailsFactory)->objectType(static::class);

        $objectSchema = new ObjectSchema(
            $typeDetails,
            static::class,
            $classInfo->getClassDescription(),
            $propertySchemas,
            $required,
        );
        return $objectSchema;
    }
}