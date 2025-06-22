<?php

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Messages;

class OpenAIBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        $messages = match($this->supportsAlternatingRoles($request)) {
            false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
            true => $request->messages(),
        };

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $requestBody['stream_options']['include_usage'] = true;
        }

        $requestBody['response_format'] = match(true) {
            $request->hasTools() && !$this->supportsNonTextResponseForTools($request) => [],
            $this->supportsStructuredOutput($request) => $this->toResponseFormat($request),
            default => [],
        };

        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return $this->filterEmptyValues($requestBody);
    }

    // CAPABILITIES ///////////////////////////////////////////

    protected function supportsToolSelection(InferenceRequest $request) : bool {
        return true;
    }

    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        return true;
    }

    protected function supportsAlternatingRoles(InferenceRequest $request) : bool {
        return true;
    }

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return true;
    }

    // INTERNAL ///////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
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
        $toolName = $toolChoice['function']['name'] ?? null;

        $result = match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            !empty($toolName) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                ]
            ],
            default => '',
        };

        if (!$this->supportsToolSelection($request)) {
            $result = is_array($result) ? 'auto' : $result;
        }

        return $result;
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
               'x-title',
               'x-php-class',
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