<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Mistral;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Arrays;

class MistralBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $request = $request->withCacheApplied();

        $options = array_merge($this->config->options, $request->options());

        unset($options['parallel_tool_calls']);

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($request->messages()),
        ]), $options);

        $requestBody['response_format'] = match(true) {
            $request->hasTools() && !$this->supportsNonTextResponseForTools($request) => [],
            $this->supportsStructuredOutput($request) => $this->toResponseFormat($request),
            default => [],
        };
        
        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request->tools(), $request->toolChoice());
        }

        return array_filter($requestBody, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    // CAPABILITIES /////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    protected function supportsStructuredOutput(InferenceRequest $request) : bool {
        return true;
    }

    // INTERNAL /////////////////////////////////////////////

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

        return array_filter($result, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    private function toTools(InferenceRequest $request) : array {
        return $this->removeDisallowedEntries($request->tools());
    }

    private function toToolChoice(array $tools, array|string $toolChoice) : array|string {
        return match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            is_array($toolChoice) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice['function']['name'],
                ]
            ],
            default => $toolChoice,
        };
    }

    private function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
            ],
        );
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

    protected function toSchemaData(InferenceRequest $request) : array {
        $responseFormat = $request->responseFormat();
        return [
            $responseFormat->schemaFilteredWith($this->removeDisallowedEntries(...)),
            $responseFormat->schemaName(),
            $responseFormat->strict(),
        ];
    }
}