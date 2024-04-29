<?php

namespace Cognesy\Instructor\Extras\Sequences\Traits;

use Cognesy\Instructor\Extras\Sequences\Sequence;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

trait ProvidesSchema
{
    public function toSchema(): Schema {
        $schemaFactory = new SchemaFactory(false);
        $typeDetailsFactory = new TypeDetailsFactory();
        $nestedSchema = $schemaFactory->schema($this->class);
        $nestedTypeDetails = $typeDetailsFactory->fromTypeName($this->class);
        $arrayTypeDetails = new TypeDetails(
            type: 'array',
            class: null,
            nestedType: $nestedTypeDetails,
            enumType: null,
            enumValues: null,
        );
        $arraySchema = new ArraySchema(
            type: $arrayTypeDetails,
            name: 'list',
            description: '',
            nestedItemSchema: $nestedSchema,
        );
        $objectSchema = new ObjectSchema(
            type: new TypeDetails(
                type: 'object',
                class: Sequence::class,
                nestedType: null,
                enumType: null,
                enumValues: null,
            ),
            name: $this->name ?: ('sequenceOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A sequence of ' . $this->class),
        );
        $objectSchema->properties['list'] = $arraySchema;
        $objectSchema->required = ['list'];
        return $objectSchema;
    }
}