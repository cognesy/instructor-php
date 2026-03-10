<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Data\ToolDefinitions;

final readonly class SchemaRendering
{
    public function __construct(
        private array $jsonSchema,
        private ToolDefinitions $toolDefinitions,
    ) {}

    public function jsonSchema() : array {
        return $this->jsonSchema;
    }

    public function toolDefinitions() : ToolDefinitions {
        return $this->toolDefinitions;
    }
}
