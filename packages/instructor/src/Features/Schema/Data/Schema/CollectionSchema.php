<?php
namespace Cognesy\Instructor\Features\Schema\Data\Schema;

use Cognesy\Instructor\Features\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Features\Schema\Data\TypeDetails;

class CollectionSchema extends Schema
{
    public Schema $nestedItemSchema;

    public function __construct(
        TypeDetails $type,
        string $name,
        string $description,
        Schema $nestedItemSchema,
    ) {
        parent::__construct($type, $name, $description);
        $this->nestedItemSchema = $nestedItemSchema;
    }

    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitCollectionSchema($this);
    }
}
