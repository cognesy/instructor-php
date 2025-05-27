<?php

namespace Cognesy\Schema\Factories\Traits\SchemaFactory;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Utils\ClassInfo;

trait HandlesFactoryMethods
{
    public function string(string $name = '', string $description = ''): ScalarSchema {
        return new ScalarSchema(TypeDetails::string(), $name, $description);
    }

    public function int(string $name = '', string $description = ''): ScalarSchema {
        return new ScalarSchema(TypeDetails::int(), $name, $description);
    }

    public function float(string $name = '', string $description = ''): ScalarSchema {
        return new ScalarSchema(TypeDetails::float(), $name, $description);
    }

    public function bool(string $name = '', string $description = ''): ScalarSchema {
        return new ScalarSchema(TypeDetails::bool(), $name, $description);
    }

    public function array(string $name = '', string $description = ''): ArraySchema {
        return new ArraySchema(TypeDetails::array(), $name, $description);
    }

    public function object(string $class, string $name = '', string $description = '', $properties = [], $required = []): ObjectSchema {
        $classInfo = new ClassInfo($class);
        $properties = $properties ?: $this->getPropertySchemas($classInfo);
        $required = $required ?: ($classInfo)->getRequiredProperties();
        $name = $name ?: $classInfo->getClass();
        $description = $description ?: $classInfo->getClassDescription();
        return new ObjectSchema(TypeDetails::object($class), $name, $description, $properties, $required);
    }

    public function enum(string $class, string $name = '', string $description = ''): EnumSchema {
        return new EnumSchema(TypeDetails::enum($class), $name, $description);
    }

    public function collection(string $nestedType, string $name = '', string $description = '', ?Schema $nestedTypeSchema = null): CollectionSchema {
        $nestedTypeDetails = TypeDetails::fromTypeName($nestedType);
        $nestedSchema = $nestedTypeSchema ?? $this->makeSchema($nestedTypeDetails);
        $schema = new CollectionSchema(TypeDetails::collection($nestedTypeDetails), $name, $description, $nestedSchema);
        return $schema;
    }
}
