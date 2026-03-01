<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Data\TypeDetails;

readonly class CollectionSchema extends Schema
{
    public function __construct(
        TypeDetails $type,
        string $name,
        string $description,
        public Schema $nestedItemSchema,
    ) {
        parent::__construct($type, $name, $description);
    }
}
