<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Minimaxi;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class MinimaxiBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = [];
                break;
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                [$schema, $schemaName, $schemaStrict] = $this->toSchemaData($request);
                $schema = $this->toNativeSchema($schema, $schemaName, $schemaStrict);
                $result = ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'schema' => $schema]];
                break;
            default:
                $result = [];
        }

        return $result;
    }

    protected function toTools(InferenceRequest $request) : array {
        return $request->hasTools()
            ? $this->toNativeTools($request->tools())
            : [];
    }

    protected function toToolChoice(InferenceRequest $request) : array|string {
        return [];
    }

    protected function toNativeTools(array $tools) : array|string {
        return array_map(fn($tool) => $this->toNativeTool($tool), $tools);
    }

    protected function toNativeTool(array $tool) : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool['function']['name'] ?? $tool['name'] ?? '',
                'description' => $tool['function']['description'] ?? $tool['description'] ?? '',
                'parameters' => $tool['function']['parameters'] ?? $tool['parameters'] ?? [],
            ],
        ];
    }

    private function toNativeSchema(array $schema) : array {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // replace 'integer' or "integer" with 'number'
        $json = str_replace(['"integer"', "'integer'"], '"number"', $json);
        return json_decode($json, true) ?? [];
    }
}