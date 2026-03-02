<?php declare(strict_types=1);

namespace Cognesy\Schema\Data;

use Symfony\Component\TypeInfo\Type;

readonly class CollectionSchema extends Schema
{
    public function __construct(
        Type $type,
        string $name,
        string $description,
        public Schema $nestedItemSchema,
        bool $nullable = false,
        bool $hasDefaultValue = false,
        mixed $defaultValue = null,
    ) {
        parent::__construct(
            type: $type,
            name: $name,
            description: $description,
            nullable: $nullable,
            hasDefaultValue: $hasDefaultValue,
            defaultValue: $defaultValue,
        );
    }
}
