<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Minimaxi;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class MinimaxiBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    #[\Override]
    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        if ($mode === null) {
            return [];
        }

        // Minimaxi API supports: json_schema (with integer->number transformation)
        $responseFormat = $request->responseFormat()
            ->withToJsonObjectHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $this->toNativeSchema($request->responseFormat()->schema()),
                ],
            ])
            ->withToJsonSchemaHandler(fn() => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseFormat()->schemaName(),
                    'schema' => $this->toNativeSchema($request->responseFormat()->schema()),
                ],
            ]);

        return $responseFormat->as($mode);
    }

    #[\Override]
    protected function toTools(InferenceRequest $request) : array {
        return $request->hasTools()
            ? $this->toNativeTools($request->tools())
            : [];
    }

    #[\Override]
    protected function toToolChoice(InferenceRequest $request) : array|string {
        return [];
    }

    protected function toNativeTools(array $tools) : array {
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
        // First remove disallowed entries
        $schema = $this->removeDisallowedEntries($schema);

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [];
        }
        // replace 'integer' or "integer" with 'number'
        $json = str_replace(['"integer"', "'integer'"], '"number"', $json);
        return json_decode($json, true) ?? [];
    }
}