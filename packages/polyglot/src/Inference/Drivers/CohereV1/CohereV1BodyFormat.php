<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV1;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Messages;

class CohereV1BodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        unset($options['parallel_tool_calls']);

        $system = '';
        $chatHistory = [];
        $nativeMessages = Messages::asString($this->messageFormat->map($request->messages()));

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->defaultModel,
            'preamble' => $system,
            'chat_history' => $chatHistory,
            'message' => $nativeMessages,
        ]), $options);

        $requestBody['response_format'] = match(true) {
            $request->hasTools() && !$this->supportsNonTextResponseForTools($request) => [],
            $this->supportsStructuredOutput($request) => $this->toResponseFormat($request),
            default => [],
        };

        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['response_format'] = ['type' => 'text'];
        }

        return $this->filterEmptyValues($requestBody);
    }

    // CAPABILITIES /////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        return true;
    }

    // INTERNAL /////////////////////////////////////////////

    private function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = ['type' => 'json_object', 'schema' => $schema];
                break;
            default:
                $result = [];
        }

        return $result;
    }

    private function toTools(InferenceRequest $request): array {
        $tools = $request->tools();

        $result = [];
        foreach ($tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[$name] = array_filter([
                    'description' => $param['description'] ?? '',
                    'type' => $this->toCohereType($param),
                    'required' => in_array(
                        needle: $name,
                        haystack: $tool['function']['parameters']['required'] ?? [],
                    ),
                ]);
            }
            $result[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameterDefinitions' => $parameters,
            ];
        }
        return $result;
    }

    private function toCohereType(array $param) : string {
        return match($param['type']) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => throw new \Exception('Array type not supported by Cohere'),
            'object' => throw new \Exception('Object type not supported by Cohere'),
            default => throw new \Exception('Unknown type'),
        };
    }

    private function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                'additionalProperties',
            ],
        );
    }

    private function filterEmptyValues(array $data) : array {
        return array_filter($data, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

        protected function toSchemaData(InferenceRequest $request) : array {
        $responseFormat = $request->responseFormat();

        $schema = $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [];
        $schema = $this->removeDisallowedEntries($schema);

        $schemaName = $responseFormat['json_schema']['name'] ?? $responseFormat['name'] ?? 'schema';
        $schemaStrict = $responseFormat['json_schema']['strict'] ?? $responseFormat['strict'] ?? true;

        return [
            $schema,
            $schemaName,
            $schemaStrict,
        ];
    }

    protected function toResponseFormatMode(InferenceRequest $request) : ?OutputMode {
        if (!$request->outputMode()?->is(OutputMode::Unrestricted)) {
            return $request->outputMode();
        }
        if ($request->hasTextResponseFormat()) {
            return OutputMode::Text;
        }
        if (!$request->hasResponseFormat()) {
            return null;
        }

        $responseFormat = $request->responseFormat();
        $type = $responseFormat['type'] ?? $responseFormat['json_schema']['type'] ?? '';
        return match($type) {
            'json' => OutputMode::Json,
            'json_object' => OutputMode::Json,
            'json_schema' => OutputMode::JsonSchema,
            default => null,
        };
    }
}