<?php

namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Schema\Factories\SchemaBuilder;
use Cognesy\Instructor\Utils\Json;
use Override;
use Saloon\Http\Response;

class AnthropicApiRequest extends ApiRequest
{
    protected string $defaultEndpoint = '/messages';
    protected string $xmlLineSeparator = "";

    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        $content = $decoded['content'][0]['text'] ?? '';
        $finishReason = $decoded['stop_reason'] ?? '';
        $inputTokens = $decoded['delta']['input_tokens'] ?? 0;
        $outputTokens = $decoded['delta']['output_tokens'] ?? 0;
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            toolName: '',
            finishReason: $finishReason,
            toolCalls: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['delta']['text'] ?? '';
        $inputTokens = $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $outputTokens = $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $finishReason = $decoded['message']['stop_reason'] ?? $decoded['message']['stop_reason'] ?? '';
        return new PartialApiResponse(
            delta: $delta,
            responseData: $decoded,
            toolName: '',
            finishReason: $finishReason,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////

    #[Override]
    protected function getResponseFormat(): array {
        return [];
    }

    #[Override]
    protected function defaultBody(): array {
        $body = array_filter(array_merge([
            'system' => $this->getSystemInstruction(),
            'messages' => $this->messages(),
            'model' => $this->model,
        ], $this->options));
        return $body;
    }

    protected function getToolSchema(): array {
        return $this->tools[0]['function']['parameters'] ?? $this->responseFormat['schema'] ?? [];
    }

    protected function getSystemInstruction() : string {
        $tool = $this->getToolSchema();
        $schema = (new SchemaBuilder)->fromArray($tool);
        $xmlSchema = $schema->toXml();
        $system = $this->instructions()."\nHere are the tools available:\n".$this->xmlToolSchema($xmlSchema);
        return $system;
    }

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