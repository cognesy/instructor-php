<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct\Actions;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Contracts\Decision;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final class MakeToolCalls
{
    public function __construct(
        private readonly Tools $tools,
        private readonly ReActValidator $validator,
    ) {}

    public function __invoke(Decision $decision) : ToolCalls {
        if (!$decision->isCall()) {
            return ToolCalls::empty();
        }
        $toolName = $decision->tool() ?? '';
        $argStructure = $this->buildToolArgStructure($toolName);
        $argsValidation = $this->validator->validateArgsForCall($decision, $argStructure);
        if ($argsValidation->isInvalid()) {
            return ToolCalls::empty();
        }
        $normalizedArgs = $this->normalizeArgsForTool($toolName, $decision->args(), $argStructure);
        return new ToolCalls(new ToolCall($toolName, $normalizedArgs));
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function buildToolArgStructure(string $toolName): ?Structure {
        if ($toolName === '' || !$this->tools->has($toolName)) {
            return null;
        }
        $schema = $this->tools->get($toolName)->toToolSchema();
        $parameters = $schema['function']['parameters'] ?? null;
        if (!is_array($parameters)) {
            return null;
        }
        return StructureFactory::fromJsonSchema([
            ...$parameters,
            'x-title' => $parameters['x-title'] ?? $toolName . '_arguments',
            'description' => $parameters['description'] ?? ('Arguments for ' . $toolName),
        ]);
    }

    private function normalizeArgsForTool(string $toolName, array $args, ?Structure $argStructure): array {
        if ($toolName === '' || $argStructure === null) {
            return $args;
        }
        $structure = $argStructure->clone();
        foreach ($args as $key => $value) {
            // Skip numeric keys - they indicate the LLM returned a list instead of an object
            if (!is_string($key)) {
                continue;
            }
            if ($structure->has($key)) {
                $structure->set($key, $value);
            }
        }
        $normalized = $structure->toArray();
        return array_filter(
            $normalized,
            static fn($value) => $value !== null,
        );
    }
}

