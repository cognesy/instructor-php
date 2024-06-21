<?php

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Schema\Data\Schema\CollectionSchema;
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
        $collectionTypeDetails = $typeDetailsFactory->collectionType($nestedTypeDetails->toString());
        $collectionSchema = new CollectionSchema(
            type: $collectionTypeDetails,
            name: 'list',
            description: '',
            nestedItemSchema: $nestedSchema,
        );
        $objectType = $typeDetailsFactory->objectType(Sequence::class);
        $objectSchema = new ObjectSchema(
            type: $objectType,
            name: $this->name ?: ('collectionOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A collection of ' . $this->class),
        );
        $objectSchema->properties['list'] = $collectionSchema;
        $objectSchema->required = ['list'];
        return $objectSchema;
    }
}