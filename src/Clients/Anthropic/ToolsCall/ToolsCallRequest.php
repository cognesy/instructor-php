<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiToolsCallRequest;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $prompt = "\nExtract correct and accurate data from the messages using provided tools. Response must be JSON object following provided schema.\n";
    protected string $endpoint = '/messages';
    protected string $xmlLineSeparator = "";

    protected function defaultBody(): array {
        $system = $this->getSystemInstruction();
        $body = array_filter(array_merge($this->payload, [
            'system' => $system,
            'messages' => $this->appendInstructions($this->messages),
            'model' => $this->model,
        ], $this->options));
        return $body;
    }

    protected function getSystemInstruction() {
        $tool = $this->tools[0]['function']['parameters'];
        $schema = (new SchemaBuilder)->fromArray($tool);
        $xmlSchema = $schema->toXml();
        $system = $this->instructions()."\nHere are the tools available:\n".$this->toolSchema($xmlSchema);
        return $system;
    }

    protected function appendInstructions(array $messages) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt;
        return $messages;
    }

    public function instructions() : string {
        $lines = [
            "In this environment you have access to a set of tools you can use to answer the user's question.\n",
            "You may call them like this:\n",
            '<function_calls>',
            '<invoke>',
            '<tool_name>$TOOL_NAME</tool_name>',
            '<parameters>',
            '<$PARAMETER_NAME>$PARAMETER_VALUE</$PARAMETER_NAME>',
            '...',
            '</parameters>',
            '</invoke>',
            '</function_calls>',
        ];
        return implode($this->xmlLineSeparator, $lines);
    }

    private function toolSchema(string $xmlSchema) : string {
        $lines = [
            '<tools>',
            '<tool_description>',
            '<tool_name>extract_data</tool_name>',
            '<description>',
            'Extract data from chat content',
            '</description>',
            '</tool_description>',
            '<parameters>',
            $xmlSchema,
            '</parameters>',
            '</tools>',
        ];
        return implode($this->xmlLineSeparator, $lines);
    }
}
