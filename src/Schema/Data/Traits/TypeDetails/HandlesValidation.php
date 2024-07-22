<?php

namespace Cognesy\Instructor\Schema\Data\Traits\TypeDetails;

use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesValidation
{
    private function validate(
        string $type,
        ?string $class,
        ?TypeDetails $nestedType,
        ?string $enumType,
        ?array $enumValues,
    ) : void {
        if (!in_array($type, self::PHP_TYPES)) {
            throw new \Exception('Unsupported type: '.$type);
        }

        // ...check enum
        if ($type === self::PHP_ENUM) {
            if ($class === null) {
                throw new \Exception('Enum type must have a class');
            }
            if ($enumType === null) {
                throw new \Exception('Enum type must have an enum type');
            }
            if ($enumValues === null) {
                throw new \Exception('Enum type must have enum values');
            }
        }
        // ...check array
        if (($type === self::PHP_COLLECTION) && ($nestedType === null)) {
            throw new \Exception('Collection type must have a nested type');
        }
    }
}