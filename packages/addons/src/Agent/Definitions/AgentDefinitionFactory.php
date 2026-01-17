<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Definitions;

use Cognesy\Addons\Agent\Contracts\AgentBlueprint;
use Cognesy\Addons\Agent\Registry\AgentBlueprintRegistry;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

final class AgentDefinitionFactory
{
    public function __construct(
        private readonly AgentBlueprintRegistry $blueprints,
        private readonly ?string $defaultBlueprint = null,
    ) {}

    public function create(AgentDefinition $definition): Result
    {
        try {
            $class = $this->resolveBlueprintClass($definition);
            $result = $class::fromDefinition($definition);

            if (!$result instanceof Result) {
                return Result::failure(new InvalidArgumentException(
                    "Blueprint '{$class}' must return a Result."
                ));
            }

            return $result;
        } catch (\Throwable $e) {
            return Result::failure($e);
        }
    }

    /**
     * @return class-string<AgentBlueprint>
     */
    private function resolveBlueprintClass(AgentDefinition $definition): string
    {
        if ($definition->blueprintClass !== null) {
            if (!class_exists($definition->blueprintClass)) {
                throw new InvalidArgumentException(
                    "Blueprint class '{$definition->blueprintClass}' does not exist."
                );
            }
            if (!is_subclass_of($definition->blueprintClass, AgentBlueprint::class)) {
                throw new InvalidArgumentException(
                    "Blueprint class '{$definition->blueprintClass}' must implement AgentBlueprint."
                );
            }

            return $definition->blueprintClass;
        }

        if ($definition->blueprint !== null) {
            return $this->blueprints->get($definition->blueprint);
        }

        if ($this->defaultBlueprint !== null) {
            return $this->blueprints->get($this->defaultBlueprint);
        }

        throw new InvalidArgumentException(
            "Agent definition '{$definition->id}' must specify 'blueprint' or 'blueprint_class'."
        );
    }
}
