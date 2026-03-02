<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct\Actions;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\ReAct\Contracts\Decision;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Utils\JsonSchema\JsonSchema;

final class MakeToolCalls
{
    private readonly SchemaFactory $schemaFactory;

    public function __construct(
        private readonly Tools $tools,
        private readonly ReActValidator $validator,
        ?SchemaFactory $schemaFactory = null,
    ) {
        $this->schemaFactory = $schemaFactory ?? SchemaFactory::default();
    }

    public function __invoke(Decision $decision) : ToolCalls {
        if (!$decision->isCall()) {
            return ToolCalls::empty();
        }
        $toolName = $decision->tool() ?? '';
        $argsSchema = $this->buildToolArgsSchema($toolName);
        $argsValidation = $this->validator->validateArgsForCall($decision, $argsSchema);
        if ($argsValidation->isInvalid()) {
            return ToolCalls::empty();
        }
        $normalizedArgs = $this->normalizeArgsForTool($toolName, $decision->args(), $argsSchema);
        return new ToolCalls(new ToolCall($toolName, $normalizedArgs));
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function buildToolArgsSchema(string $toolName): ?Schema {
        if ($toolName === '' || !$this->tools->has($toolName)) {
            return null;
        }

        $schema = $this->tools->get($toolName)->toToolSchema();
        $parameters = $schema['function']['parameters'] ?? null;
        if (!is_array($parameters)) {
            return null;
        }

        $jsonSchema = JsonSchema::fromArray([
            ...$parameters,
            'x-title' => $parameters['x-title'] ?? $toolName . '_arguments',
            'description' => $parameters['description'] ?? ('Arguments for ' . $toolName),
        ]);

        return $this->schemaFactory->schemaParser()->parse($jsonSchema);
    }

    private function normalizeArgsForTool(string $toolName, array $args, ?Schema $argsSchema): array {
        if ($toolName === '' || $argsSchema === null) {
            return $args;
        }

        $stringKeyArgs = array_filter(
            $args,
            static fn(mixed $key) : bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );

        $normalized = $this->normalizeBySchema($argsSchema, $stringKeyArgs);
        if (!is_array($normalized)) {
            return $stringKeyArgs;
        }

        return array_filter(
            $normalized,
            static fn($value) => $value !== null,
        );
    }

    private function normalizeBySchema(Schema $schema, mixed $value) : mixed {
        if ($schema instanceof CollectionSchema) {
            if (!is_array($value)) {
                return $value;
            }

            $normalized = [];
            foreach ($value as $item) {
                $normalized[] = $this->normalizeBySchema($schema->nestedItemSchema, $item);
            }
            return $normalized;
        }

        if (!($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema)) {
            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($schema->getPropertySchemas() as $name => $propertySchema) {
            if (array_key_exists($name, $value)) {
                $normalized[$name] = $this->normalizeBySchema($propertySchema, $value[$name]);
                continue;
            }

            if ($propertySchema->hasDefaultValue()) {
                $normalized[$name] = $propertySchema->defaultValue();
            }
        }

        return $normalized;
    }
}
