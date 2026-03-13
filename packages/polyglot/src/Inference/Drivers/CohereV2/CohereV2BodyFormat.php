<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Utils\Arrays;

class CohereV2BodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        if (array_key_exists('max_completion_tokens', $requestBody)
            && !array_key_exists('max_tokens', $requestBody)
        ) {
            $requestBody['max_tokens'] = $requestBody['max_completion_tokens'];
        }
        unset($requestBody['max_completion_tokens']);
        // Cohere V2 does not support some OpenAI params, so we unset it
        unset($requestBody['tool_choice']);
        unset($requestBody['parallel_tool_calls']);
        unset($requestBody['stream_options']);

        return $requestBody;
    }

    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    // INTERNAL //////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $type = $this->toResponseFormatType($request);
        if ($type === null) {
            return [];
        }

        // Cohere V2 API supports: json_object with schema, text
        $schema = $this->normalizeSchemaForCohere(
            $this->removeDisallowedEntries($request->responseFormat()->schema()),
        );
        $jsonObject = empty($schema)
            ? ['type' => 'json_object']
            : ['type' => 'json_object', 'schema' => $schema];
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => $jsonObject)
            ->withToJsonSchemaHandler(fn() => $jsonObject);

        return $this->renderResponseFormatForType($responseFormat, $type);
    }

    #[\Override]
    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                'additionalProperties',
                'nullable',
            ],
        );
    }

    protected function normalizeSchemaForCohere(array $schema): array {
        $properties = $schema['properties'] ?? null;
        if (is_array($properties)) {
            $normalizedProperties = [];
            foreach ($properties as $name => $propertySchema) {
                $normalizedProperties[$name] = is_array($propertySchema)
                    ? $this->normalizeSchemaForCohere($propertySchema)
                    : $propertySchema;
            }
            $schema['properties'] = $normalizedProperties;
            $schema['required'] = array_keys($normalizedProperties);
        }

        $items = $schema['items'] ?? null;
        if (is_array($items)) {
            $schema['items'] = $this->normalizeSchemaForCohere($items);
        }

        foreach (['$defs', 'definitions'] as $key) {
            $definitions = $schema[$key] ?? null;
            if (!is_array($definitions)) {
                continue;
            }

            $normalizedDefinitions = [];
            foreach ($definitions as $definitionName => $definitionSchema) {
                $normalizedDefinitions[$definitionName] = is_array($definitionSchema)
                    ? $this->normalizeSchemaForCohere($definitionSchema)
                    : $definitionSchema;
            }
            $schema[$key] = $normalizedDefinitions;
        }

        foreach (['anyOf', 'oneOf', 'allOf', 'prefixItems'] as $key) {
            $variants = $schema[$key] ?? null;
            if (!is_array($variants)) {
                continue;
            }

            $normalizedVariants = [];
            foreach ($variants as $variant) {
                $normalizedVariants[] = is_array($variant)
                    ? $this->normalizeSchemaForCohere($variant)
                    : $variant;
            }
            $schema[$key] = $normalizedVariants;
        }

        return $schema;
    }
}
