<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Minimaxi;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use InvalidArgumentException;
class MinimaxiBodyFormat extends OpenAICompatibleBodyFormat
{
    // INTERNAL ///////////////////////////////////////////////

    // Minimaxi only speaks json_schema, so plain JSON mode is sent as a schema payload too.
    #[\Override]
    protected function toJsonObjectResponseFormat(ResponseFormat $responseFormat) : array {
        return $this->toJsonSchemaResponseFormat($responseFormat);
    }

    // No `strict` flag, and integers are rewritten to numbers -- see toNativeSchema().
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $responseFormat->schemaName(),
                'schema' => $this->toNativeSchema($responseFormat->schema()),
            ],
        ];
    }

    #[\Override]
    protected function toTools(InferenceRequest $request) : array {
        return $request->hasTools()
            ? $this->toNativeTools($request->tools())
            : [];
    }

    #[\Override]
    protected function toToolChoice(InferenceRequest $request) : array|string {
        if ($request->hasToolChoice()) {
            throw new InvalidArgumentException('MiniMax cannot render an explicit tool choice.');
        }

        return [];
    }

    protected function toNativeTools(\Cognesy\Polyglot\Inference\Data\ToolDefinitions $tools) : array {
        return array_map(fn($tool) => $this->toNativeTool($tool), $tools->all());
    }

    protected function toNativeTool(\Cognesy\Polyglot\Inference\Data\ToolDefinition $tool) : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ];
    }

    private function toNativeSchema(array $schema) : array {
        // First remove disallowed entries
        $schema = $this->removeDisallowedEntries($schema);

        if ($this->containsIntegerType($schema)) {
            throw new InvalidArgumentException(
                'MiniMax cannot render integer schema types without changing them to number.',
            );
        }

        return $schema;
    }

    private function containsIntegerType(array $schema): bool {
        foreach ($schema as $key => $value) {
            if ($key === 'type' && $value === 'integer') {
                return true;
            }
            if (is_array($value) && $this->containsIntegerType($value)) {
                return true;
            }
        }

        return false;
    }
}
