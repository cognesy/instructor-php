<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Requests\ApiToolsCallRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Schema\Factories\SchemaBuilder;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $prompt = "\nExtract correct and accurate data from the messages using provided tools. Response must be JSON object following provided schema.\n";
    protected string $defaultEndpoint = '/messages';
    protected string $xmlLineSeparator = "";

    protected function defaultBody(): array {
        $body = array_filter(array_merge([
            'system' => $this->getSystemInstruction(),
            'messages' => $this->getMessages(),
            'model' => $this->model,
        ], $this->options));
        return $body;
    }

    protected function getSystemInstruction() {
        $tool = $this->getToolSchema();
        $schema = (new SchemaBuilder)->fromArray($tool);
        $xmlSchema = $schema->toXml();
        $system = $this->instructions()."\nHere are the tools available:\n".$this->toolSchema($xmlSchema);
        return $system;
    }

    protected function getMessages(): array {
        return $this->appendInstructions($this->messages, $this->prompt, $this->getToolSchema());
    }

    protected function getToolSchema(): array {
        return $this->tools[0]['function']['parameters'];
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

    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response);
        $content = $decoded['content'][0]['text'] ?? '';
        //[$functionName, $args] = (new XmlExtractor)->extractToolCalls($content);
        //return new self($args, $decoded, $functionName);
        $finishReason = $decoded['stop_reason'] ?? '';
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            functionName: '',
            finishReason: $finishReason,
            toolCalls: null
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $argumentsJson = $decoded['delta']['text'] ?? '';
        return new PartialApiResponse($argumentsJson, $decoded);
    }
}
