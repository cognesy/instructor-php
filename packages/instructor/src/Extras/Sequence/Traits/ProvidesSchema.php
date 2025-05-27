<?php
namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;

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
