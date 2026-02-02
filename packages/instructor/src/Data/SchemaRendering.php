<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

final readonly class SchemaRendering
{
    public function __construct(
        private array $jsonSchema,
        private array $toolCallSchema,
    ) {}

    public function jsonSchema() : array {
        return $this->jsonSchema;
    }

    public function toolCallSchema() : array {
        return $this->toolCallSchema;
    }
}
