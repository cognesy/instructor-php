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
    public string $retryPrompt = "JSON generated incorrectly, fix following errors";

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

//'<tool_description>',
//'<tool_name>get_weather</tool_name>',
//'<description>',
////'Retrieves the current weather for a specified location.',
////'Returns a dictionary with two fields:',
////'- temperature: float, the current temperature in Fahrenheit',
////'- conditions: string, a brief description of the current weather conditions',
////'Raises ValueError if the provided location cannot be found.',
//'</description>',
//$this->parameters($parameters),
//'</tool_description>',
