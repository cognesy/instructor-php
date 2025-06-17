<?php

namespace Cognesy\Schema\Data\Schema\Traits\Schema;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;

trait HandlesFactoryMethods
{
    public static function fromTypeName(string $typeName) : Schema {
        return self::factory()->schema($typeName);
    }

    public static function string(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->string($name, $description);
    }

    public static function int(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->int($name, $description);
    }

    public static function float(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->float($name, $description);
    }

    public static function bool(string $name = '', string $description = ''): ScalarSchema {
        return self::factory()->bool($name, $description);
    }

    public static function array(string $name = '', string $description = ''): ArraySchema {
        return self::factory()->array($name, $description);
    }

    public static function object(string $class, string $name = '', string $description = '', $properties = [], $required = []): ObjectSchema {
        return self::factory()->object($class, $name, $description, $properties, $required);
    }

    public static function enum(string $class, string $name = '', string $description = ''): EnumSchema {
        return self::factory()->enum($class, $name, $description);
    }

    public static function collection(string $nestedType, string $name = '', string $description = '', ?Schema $nestedItemSchema = null): CollectionSchema {
        return self::factory()->collection($nestedType, $name, $description, $nestedItemSchema);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    protected static function factory(): SchemaFactory {
        return new SchemaFactory(
            useObjectReferences: false,
            schemaConverter: new JsonSchemaToSchema(),
        );
    }
}