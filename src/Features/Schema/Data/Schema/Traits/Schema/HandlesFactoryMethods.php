<?php

namespace Cognesy\Instructor\Features\Schema\Data\Schema\Traits\Schema;

use Cognesy\Instructor\Features\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\CollectionSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;

trait HandlesFactoryMethods
{
    public static function fromTypeName(string $typeName) : Schema {
        return (new SchemaFactory)->schema($typeName);
    }

    public static function string(string $name = '', string $description = ''): ScalarSchema {
        return (new SchemaFactory)->string($name, $description);
    }

    public static function int(string $name = '', string $description = ''): ScalarSchema {
        return (new SchemaFactory)->int($name, $description);
    }

    public static function float(string $name = '', string $description = ''): ScalarSchema {
        return (new SchemaFactory)->float($name, $description);
    }

    public static function bool(string $name = '', string $description = ''): ScalarSchema {
        return (new SchemaFactory)->bool($name, $description);
    }

    public static function array(string $name = '', string $description = ''): ArraySchema {
        return (new SchemaFactory)->array($name, $description);
    }

    public static function object(string $class, string $name = '', string $description = '', $properties = [], $required = []): ObjectSchema {
        return (new SchemaFactory)->object($class, $name, $description, $properties, $required);
    }

    public static function enum(string $class, string $name = '', string $description = ''): EnumSchema {
        return (new SchemaFactory)->enum($class, $name, $description);
    }

    public static function collection(string $nestedType, string $name = '', string $description = ''): CollectionSchema {
        return (new SchemaFactory)->collection($nestedType, $name, $description);
    }
}