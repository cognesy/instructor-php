<?php

namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use JetBrains\PhpStorm\Deprecated;
use Override;

class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesTools;

    protected string $defaultEndpoint = '/messages';

    #[Deprecated]
    protected string $xmlLineSeparator = "";

    /////////////////////////////////////////////////////////////////////////////////////////////////

    #[Override]
    protected function defaultBody(): array {
        $body = array_filter(array_merge([
            //'system' => $this->getSystemInstruction(),
            'messages' => $this->messages(),
            'model' => $this->model,
        ], $this->options));
        return $body;
    }

    #[Deprecated]
    protected function getSystemInstruction() : string {
        $tool = $this->getToolSchema();
        $schema = (new SchemaFactory)->fromJsonSchema($tool, 'extract_data', 'Extract data from chat content');
        $xmlSchema = $schema->toXml();
        $system = $this->instructions()."\nHere are the tools available:\n".$this->xmlToolSchema($xmlSchema);
        return $system;
    }

    #[Deprecated]
    protected function instructions() : string {
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

    #[Deprecated]
    private function xmlToolSchema(string $xmlSchema) : string {
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