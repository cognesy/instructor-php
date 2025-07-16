<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Structure\Traits\StructureFactory;

use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Schema\Data\TypeDetails;

trait CreatesStructureFromArray
{
    static public function fromArrayKeyValues(string $name, array $data, string $description = '') : Structure {
        $fields = self::makeArrayFields($data);
        return Structure::define($name, $fields, $description);
    }

    static private function makeArrayFields(array $data) : array {
        $fields = [];
        foreach ($data as $name => $value) {
            $typeDetails = TypeDetails::fromValue($value);
            $fields[] = FieldFactory::fromTypeDetails($name, $typeDetails, '')->optional();
        }
        return $fields;
    }
}