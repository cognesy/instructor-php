<?php declare(strict_types=1);

namespace Cognesy\Instructor\Config;

class PartialsGeneratorConfig
{
    public readonly bool $matchToExpectedFields;
    public readonly bool $preventJsonSchema;

    public function __construct(
        bool $matchToExpectedFields = false,
        bool $preventJsonSchema = false,
    ) {
        $this->matchToExpectedFields = $matchToExpectedFields;
        $this->preventJsonSchema = $preventJsonSchema;
    }
}