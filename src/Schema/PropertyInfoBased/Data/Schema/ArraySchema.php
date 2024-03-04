<?php
namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\TypeDetails;

class ArraySchema extends Schema
{
    private Schema $nestedItemSchema;

    public function __construct(
        TypeDetails $type,
        string $name,
        string $description,
        Schema $nestedItemSchema,
    ) {
        parent::__construct($type, $name, $description);
        $this->nestedItemSchema = $nestedItemSchema;
    }

    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => 'array',
            'items' => $this->nestedItemSchema->toArray($refCallback),
            'description' => $this->description,
        ]);
    }
}
