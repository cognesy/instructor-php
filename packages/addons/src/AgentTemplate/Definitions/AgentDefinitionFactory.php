<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Definitions;

use Cognesy\Addons\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Addons\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintCreationException;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintMissingException;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Addons\AgentTemplate\Exceptions\InvalidAgentBlueprintException;
use Cognesy\Addons\AgentTemplate\Registry\AgentBlueprintRegistry;
use Throwable;

final class AgentDefinitionFactory
{
    public function __construct(
        private readonly AgentBlueprintRegistry $blueprints,
        private readonly ?string $defaultBlueprint = null,
    ) {}

    public function create(AgentDefinition $definition): AgentInterface
    {
        $class = $this->resolveBlueprintClass($definition);

        try {
            return $class::fromDefinition($definition);
        } catch (AgentBlueprintCreationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AgentBlueprintCreationException(
                "Failed to create agent from blueprint '{$class}' for definition '{$definition->id}'.",
                previous: $e,
            );
        }
    }

    /**
     * @return class-string<AgentBlueprint>
     */
    private function resolveBlueprintClass(AgentDefinition $definition): string
    {
        if ($definition->blueprintClass !== null) {
            if (!class_exists($definition->blueprintClass)) {
                throw new AgentBlueprintNotFoundException(
                    "Blueprint class '{$definition->blueprintClass}' does not exist."
                );
            }
            if (!is_subclass_of($definition->blueprintClass, AgentBlueprint::class)) {
                throw new InvalidAgentBlueprintException(
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

        throw new AgentBlueprintMissingException(
            "Agent definition '{$definition->id}' must specify 'blueprint' or 'blueprint_class'."
        );
    }
}
