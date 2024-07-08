<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\SchemaConverter;
//use Cognesy\Instructor\Extras\Structure\Field;
//use Cognesy\Instructor\Extras\Structure\FieldFactory;
//use Cognesy\Instructor\Schema\Data\TypeDetails;
//use Exception;

trait CreatesStructureFromJsonSchema
{
    static public function fromJsonSchema(array $jsonSchema) : Structure {
        $name = $jsonSchema['title'] ?? '';
        $description = $jsonSchema['description'] ?? '';
        $schema = (new SchemaConverter)->fromJsonSchema($jsonSchema);
        return self::fromSchema($name, $schema, $description);
//        $fields = self::makeJsonSchemaFields($jsonSchema);
//        return Structure::define($name, $fields, $description);
    }

//    /**
//     * @param array $jsonSchema
//     * @return Field[]
//     */
//    static private function makeJsonSchemaFields(array $jsonSchema) : array {
//        $fields = [];
//        foreach ($jsonSchema['properties'] as $name => $value) {
//            $typeName = TypeDetails::toPhpType($name);
//            //$typeName = match(true) {
//            //    isset($value['enum']) => TypeDetails::PHP_ENUM,
//            //    isset($value['items']) => TypeDetails::PHP_COLLECTION,
//            //    isset($value['properties']) => TypeDetails::PHP_OBJECT,
//            //    default => TypeDetails::toPhpType($value['type']),
//            //};
//            $typeDetails = match($typeName) {
//                TypeDetails::PHP_ENUM => TypeDetails::enum($value['x-php-class']),
//                TypeDetails::PHP_ARRAY => TypeDetails::collection(self::getCollectionType($value)),
//                TypeDetails::PHP_OBJECT => TypeDetails::object($name),
//                default => TypeDetails::fromTypeName($typeName),
//            };
//            $fields[] = FieldFactory::fromTypeName($name, $typeName, $value['description'] ?? '');
//        }
//        return $fields;
//    }
//
//    static private function fromJsonArray(array $jsonSchema) : TypeDetails {
//        // check if array is collection of items of specific type or just unstructured array
//        $items = $jsonSchema['items'];
//        // return TypeDetails::collection() or TypeDetails::array()
//
//    }
//
//    static private function getCollectionType(array $jsonSchema) : TypeDetails {
//        $items = $jsonSchema['items'];
//        $typeName = match(true) {
//            isset($items['enum']) => TypeDetails::PHP_ENUM,
//            isset($items['items']) => TypeDetails::PHP_COLLECTION,
//            isset($items['properties']) => TypeDetails::PHP_OBJECT,
//            default => TypeDetails::toPhpType($items['type']),
//        };
//        return match($typeName) {
//            TypeDetails::PHP_ENUM => TypeDetails::enum($items['x-php-class']),
//            TypeDetails::PHP_COLLECTION => throw new Exception('Nested collections are not supported'),
//            TypeDetails::PHP_OBJECT => TypeDetails::object($items['x-php-class']),
//            default => TypeDetails::fromTypeName($typeName),
//        };
//    }
}