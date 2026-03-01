<?php declare(strict_types=1);

namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\Schema;
use Cognesy\Utils\JsonSchema\JsonSchema;

interface CanParseJsonSchema
{
    public function parse(JsonSchema $jsonSchema) : Schema;
}
