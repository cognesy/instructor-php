<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\StructureFactory;

use Cognesy\Dynamic\FieldFactory;
use Cognesy\Dynamic\Structure;
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