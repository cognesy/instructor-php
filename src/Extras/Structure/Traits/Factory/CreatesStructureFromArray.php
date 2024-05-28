<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

trait CreatesStructureFromArray
{
    static public function fromArrayKeyValues(string $name, array $data, string $description = '') : Structure {
        $fields = self::makeArrayFields($data);
        return Structure::define($name, $fields, $description);
    }

    static private function makeArrayFields(array $data) : array {
        $fields = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($data as $name => $value) {
            $typeDetails = $typeDetailsFactory->fromValue($value);
            $fields[] = FieldFactory::fromTypeDetails($name, $typeDetails, '')->optional();
        }
        return $fields;
    }
}