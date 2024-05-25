<?php

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

trait ProvidesSchema
{
    public function toSchema(): Schema {
        $schemaFactory = new SchemaFactory(false);
        $typeDetailsFactory = new TypeDetailsFactory();

        $nestedSchema = $schemaFactory->schema($this->class);
        $nestedTypeDetails = $typeDetailsFactory->fromTypeName($this->class);
        $arrayTypeDetails = $typeDetailsFactory->arrayType($nestedTypeDetails->toString());
        $arraySchema = new ArraySchema(
            type: $arrayTypeDetails,
            name: 'list',
            description: '',
            nestedItemSchema: $nestedSchema,
        );
        $objectType = $typeDetailsFactory->objectType(Sequence::class);
        $objectSchema = new ObjectSchema(
            type: $objectType,
            name: $this->name ?: ('sequenceOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A sequence of ' . $this->class),
        );
        $objectSchema->properties['list'] = $arraySchema;
        $objectSchema->required = ['list'];
        return $objectSchema;
    }
}