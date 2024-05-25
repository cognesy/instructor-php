<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

trait CreatesFromString
{
    static public function fromString(string $name, string $typeString, string $description = '') : Structure {
        // Input format is:
        // 1) field1:string, field2:int, ...
        // 2) array{field1: string, field2: int, ...}
        // Additionally, you can add a description in () brackets:
        // field1:string (description), field2:int (description), ...
        $typeString = trim($typeString);
        if (str_starts_with($typeString, 'array{') && str_ends_with($typeString, '}')) {
            $typeString = substr($typeString, 6, -1);
        }
        $items = explode(',', $typeString);
        $fields = self::makeFieldsFromStrings($items);
        return Structure::define($name, $fields, $description);
    }

    /** @param string[] $data */
    /** @return Field[] */
    static private function makeFieldsFromStrings(array $items) : array {
        $fields = [];
        foreach ($items as $item) {
            $description = self::extractDescription($item);
            $item = self::removeDescription($item);
            [$name, $typeName] = self::parseStringParam($item);
            $fields[] = Field::fromTypeName($name, $typeName, $description);
        }
        return $fields;
    }

    static private function extractDescription(string $item) : string {
        // possible formats: field1:string (description), field2:int (description), ...
        $item = trim($item);
        $parts = explode('(', $item);
        $description = '';
        if (count($parts) > 1) {
            $description = substr($parts[1], 0, -1);
        }
        return $description;
    }

    static private function removeDescription(string $item) : string {
        $parts = explode('(', $item);
        return $parts[0];
    }

    /** @return array{string, string} */
    static private function parseStringParam(string $paramString) : array {
        $paramString = str_replace(' ', '', $paramString);
        $parts = explode(':', $paramString);
        if (count($parts) > 2) {
            throw new \InvalidArgumentException('Invalid parameter string');
        }
        return [
            trim($parts[0]),
            trim($parts[1] ?? 'string')
        ];
    }
}