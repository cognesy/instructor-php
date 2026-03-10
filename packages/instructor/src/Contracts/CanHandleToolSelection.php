<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

interface CanHandleToolSelection extends CanProvideJsonSchema, CanProvideSchema
{
    public function toToolDefinitions(): ToolDefinitions;
}