<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct\Actions;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\ReAct\Contracts\Decision;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final class MakeToolCalls
{
    private readonly StructureFactory $structureFactory;

    public function __construct(
        private readonly Tools $tools,
        private readonly ReActValidator $validator,
        ?StructureFactory $structureFactory = null,
    ) {
        $this->structureFactory = $structureFactory ?? new StructureFactory();
    }

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
        return $this->structureFactory->fromJsonSchema([
            ...$parameters,
            'x-title' => $parameters['x-title'] ?? $toolName . '_arguments',
            'description' => $parameters['description'] ?? ('Arguments for ' . $toolName),
        ]);
    }

    private function normalizeArgsForTool(string $toolName, array $args, ?Structure $argStructure): array {
        if ($toolName === '' || $argStructure === null) {
            return $args;
        }
        $stringKeyArgs = array_filter(
            $args,
            static fn(mixed $key) : bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
        $normalized = $argStructure->normalizeRecord($stringKeyArgs);

        return array_filter(
            $normalized,
            static fn($value) => $value !== null,
        );
    }
}
