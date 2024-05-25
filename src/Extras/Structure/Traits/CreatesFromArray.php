<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

trait CreatesFromArray
{
    static public function fromArrayKeyValues(string $name, array $data, string $description = '') : self {
        $fields = self::makeArrayFields($data);
        return Structure::define($name, $fields, $description);
    }

    static private function makeArrayFields(array $data) : array {
        $fields = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($data as $name => $value) {
            $typeDetails = $typeDetailsFactory->fromValue($value);
            $fields[] = Field::fromTypeDetails($name, $typeDetails, '')->optional();
        }
        return $fields;
    }
}