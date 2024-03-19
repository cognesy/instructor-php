<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

class ResponseModel
{
    public mixed $instance;
    public ?string $class;
    public Schema $schema;
    public array $jsonSchema;
    public ?array $functionCall;

    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract data in JSON format from provided content';
    public string $retryPrompt = "JSON generated incorrectly, fix following errors:";

    public function __construct(
        string $class = null,
        mixed  $instance = null,
        Schema $schema = null,
        array  $jsonSchema = null,
        array  $functionCall = null,
    )
    {
        $this->class = $class;
        $this->instance = $instance;
        $this->functionCall = $functionCall;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
    }
}
