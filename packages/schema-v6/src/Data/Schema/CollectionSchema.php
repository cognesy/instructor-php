<?php
namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\TypeDetails;

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
