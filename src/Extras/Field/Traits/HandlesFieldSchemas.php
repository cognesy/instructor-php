<?php
namespace Cognesy\Instructor\Extras\Field\Traits;

use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesFieldSchemas
{
    private TypeDetails $typeDetails;

    public function typeDetails() : TypeDetails {
        return $this->typeDetails;
    }

    public function nestedTypeDetails() : TypeDetails {
        return $this->typeDetails()->nestedType;
    }

//    private function makeSchema() : Schema {
//        return match($this->typeDetails->type) {
//            TypeDetails::PHP_OBJECT => $this->objectSchema(),
//            TypeDetails::PHP_ENUM => $this->enumSchema(),
//            TypeDetails::PHP_ARRAY => $this->arraySchema(),
//            default => $this->scalarSchema(),
//        };
//    }
//
//    private function objectSchema() : ObjectSchema {
//        return new ObjectSchema(
//            type: $this->typeDetails,
//            name: $this->name,
//            description: $this->description,
//        );
//    }
//
//    private function enumSchema() : Schema {
//        return new EnumSchema(
//            type: $this->typeDetails,
//            name: $this->name,
//            description: $this->description,
//        );
//    }
//
//    private function arraySchema() : Schema {
//        return new ArraySchema(
//            type: $this->typeDetails,
//            name: $this->name,
//            description: $this->description,
//            nestedItemSchema: null, // $this->nestedTypeDetails(),
//        );
//    }
//
//    private function scalarSchema() : Schema {
//        return new ScalarSchema(
//            type: $this->typeDetails,
//            name: $this->name,
//            description: $this->description,
//        );
//    }
}