<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesFieldSchemas
{
    private TypeDetails $typeDetails;
    private Field $nestedField;

    public function typeDetails() : TypeDetails {
        return $this->typeDetails;
    }

    private function makeSchema() : Schema {
        return match($this->typeDetails->type) {
            'object' => $this->objectSchema(),
            'enum' => $this->enumSchema(),
            'array' => $this->arraySchema(),
            default => $this->scalarSchema(),
        };
    }

    private function objectSchema() : ObjectSchema {
        return new ObjectSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }

    private function enumSchema() : Schema {
        return new EnumSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }

    private function arraySchema() : Schema {
        return new ArraySchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
            nestedItemSchema: $this->nestedField->schema(), // TODO: ERROR!
        );
    }

    private function scalarSchema() : Schema {
        return new ScalarSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }
}