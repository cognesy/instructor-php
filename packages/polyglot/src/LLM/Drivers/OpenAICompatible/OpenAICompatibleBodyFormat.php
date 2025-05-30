<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAICompatible;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Messages;

class OpenAICompatibleBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $options = array_merge($this->config->options, $request->options());

        $messages = match($this->supportsAlternatingRoles($request)) {
            false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
            true => $request->messages(),
        };
        $messages = $this->messageFormat->map($messages);

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $messages,
        ]), $options);

        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        $requestBody['response_format'] = $this->toResponseFormat($request);
        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return $this->filterEmptyValues($requestBody);
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        if (!$this->supportsStructuredOutput($request)) {
            return [];
        }

        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
                $result = ['type' => 'json_object'];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = ['type' => 'text'];
                break;
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $result = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $schemaName,
                        'schema' => $schema,
                        'strict' => $schemaStrict,
                    ],
                ];
                break;
            default:
                $result = [];
        }
        return $this->filterEmptyValues($result);
    }

    protected function toTools(InferenceRequest $request) : array {
        return $this->removeDisallowedEntries(
            $request->tools()
        );
    }

    protected function toToolChoice(InferenceRequest $request) : array|string {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        $result = match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            is_array($toolChoice) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice['function']['name'] ?? '',
                ]
            ],
            default => $toolChoice,
        };

        if (!$this->supportsToolSelection($request)) {
            $result = is_array($result) ? 'auto' : $result;
        }

        return $result;
    }

    protected function supportsToolSelection(InferenceRequest $request) : bool {
        return true;
    }

    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        return true;
    }

    protected function supportsAlternatingRoles(InferenceRequest $request) : bool {
        return true;
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                //'additionalProperties',
            ],
        );
    }

    protected function filterEmptyValues(array $data) : array {
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