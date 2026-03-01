<?php declare(strict_types=1);

namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\Schema;
use Cognesy\Utils\JsonSchema\JsonSchema;

interface CanRenderJsonSchema
{
    /**
     * @param callable(string):void|null $onObjectRef
     */
    public function render(Schema $schema, ?callable $onObjectRef = null) : JsonSchema;
}

