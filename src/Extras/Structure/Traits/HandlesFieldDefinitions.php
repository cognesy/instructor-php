<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use ReflectionEnum;

trait HandlesFieldDefinitions
{
    static public function int(string $name, string $description = '') : self {
        return new Field($name, $description, new TypeDetails('int'));
    }

    static public function string(string $name, string $description = '') : self {
        return new Field($name, $description, new TypeDetails('string'));
    }

    static public function float(string $name, string $description = '') : self {
        return new Field($name, $description, new TypeDetails('float'));
    }

    static public function bool(string $name, string $description = '') : self {
        return new Field($name, $description, new TypeDetails('bool'));
    }

    static public function enum(string $name, string $enumClass, string $description = '') : self {
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();
        $enumValues = array_values($reflection->getConstants());
        return new Field($name, $description, new TypeDetails('enum', $enumClass, null, $backingType, $enumValues));
    }

    static public function object(string $name, string $class, string $description = '') : self {
        return new Field($name, $description, new TypeDetails('object', $class));
    }

    static public function structure(string $name, array|callable $fields, string $description = '') : self {
        $result = new Field($name, $description, new TypeDetails('object', Structure::class));
        $result->value = Structure::define($name, $fields, $description);
        return $result;
    }

//    static public function array(Structure $structure, string $description = '') : self {
//        $result = new Field();
//        $result->typeDetails = new TypeDetails('array', null, new TypeDetails('object', Structure::class));
//        $result->value = $structure;
//        $result->description = $description;
//        return $result;
//    }

//    static public function datetime(string $format, string $description = '') : self {
//        $result = new Field();
//        $result->typeDetails = new TypeDetails('object', 'DateTime', $format);
//        return $result;
//    }

//    static public function array(Field $field) : self {
//        $result = new Field();
//        $result->nestedField = $field;
//        $result->typeDetails = new TypeDetails('array', null, $field->typeDetails, null, null);
//        return $result;
//    }
}