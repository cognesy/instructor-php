<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

class ResponseModel
{
    public mixed $instance;
    public ?string $class;
    public Schema $schema;
    public array $jsonSchema;
    public array $toolCallSchema;

    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract correct data in strict JSON format from provided content';
    public string $retryPrompt = "JSON generated incorrectly, fix following errors";

    public function __construct(
        string $class = null,
        mixed  $instance = null,
        Schema $schema = null,
        array  $jsonSchema = null,
        array  $toolCallSchema = null,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->toolCallSchema = $toolCallSchema;
    }

    public function toXml() : string {
        $lines = [
            '<tools>',
            '<tool_name>'.$this->functionName.'</tool_name>',
            '<description>'.$this->functionDescription.'</description>',
            '<parameters>'.$this->schema->toXml().'</parameters>',
            '</tools>',
        ];
        return implode("\n", $lines);
    }
}