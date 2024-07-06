<?php
namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

trait ProvidesSchema
{
    public function toSchema(): Schema {
        $collectionSchema = Schema::collection(
            nestedType: $this->class,
            name: 'list',
        );
        $nestedTypeDetails = TypeDetails::fromTypeName($this->class);
        $objectSchema = Schema::object(
            class: Sequence::class,
            name: $this->name ?: ('collectionOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A collection of ' . $this->class),
            properties: ['list' => $collectionSchema],
            required: ['list'],
        );
        return $objectSchema;
    }
}

//        $schemaFactory = new SchemaFactory(false);
//        $typeDetailsFactory = new TypeDetailsFactory();
//
//        $nestedSchema = $schemaFactory->schema($this->class);
//        $nestedTypeDetails = $typeDetailsFactory->fromTypeName($this->class);
//        $collectionTypeDetails = $typeDetailsFactory->collectionType($nestedTypeDetails->toString());
//        $collectionSchema = new CollectionSchema(
//            type: $collectionTypeDetails,
//            name: 'list',
//            description: '',
//            nestedItemSchema: $nestedSchema,
//        );
//        $objectSchema->properties['list'] = $collectionSchema;
//        $objectSchema->required = ['list'];
//        $collectionSchema = Schema::collection($this->class, 'list', '');
//        $objectSchema = Schema::object(
//            $this->class,
//            'list',
//            '',
//            ['list' => $collectionSchema],
//            ['list']
//        );
