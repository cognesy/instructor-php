<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\Type;

final class FieldFactory
{
    private static ?SchemaFactory $schemaFactory = null;

    public static function fromTypeName(string $name, string $typeName, string $description = '') : Field {
        return self::fromType($name, TypeInfo::fromTypeName($typeName), $description);
    }

    public static function fromType(string $name, Type $type, string $description = '') : Field {
        $schema = self::schemaFactory()->fromType($type, $name, $description);
        return Field::fromSchema($name, $schema);
    }

    private static function schemaFactory() : SchemaFactory {
        return self::$schemaFactory ??= SchemaFactory::default();
    }
}
