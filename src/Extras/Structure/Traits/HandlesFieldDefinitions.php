<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use ReflectionEnum;

trait HandlesFieldDefinitions
{
    static public function int(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('int');
        return $result;
    }

    static public function string(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('string');
        return $result;
    }

    static public function float(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('float');
        return $result;
    }

    static public function bool(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('bool');
        return $result;
    }

    static public function enum(string $enumClass, string $description = '') : self {
        $result = new Field();
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();
        $enumValues = array_values($reflection->getConstants());
        $result->typeDetails = new TypeDetails('enum', $enumClass, null, $backingType, $enumValues);
        return $result;
    }

    static public function object(string $class, string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('object', $class);
        return $result;
    }

    static public function structure(Structure $structure, string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('object', Structure::class);
        $result->value = $structure;
        $result->description = $description;
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