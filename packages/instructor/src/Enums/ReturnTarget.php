<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum ReturnTarget: string
{
    case Array = 'array';
    case UntypedObject = 'untyped_object';
    case TypedObject = 'typed_object';
    case SelfDeserializingObject = 'self_deserializing_object';

    public function expectsObject(): bool
    {
        return match ($this) {
            self::UntypedObject,
            self::TypedObject,
            self::SelfDeserializingObject => true,
            default => false,
        };
    }
}
