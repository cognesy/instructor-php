<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentTemplateException;
use Cognesy\Agents\AgentTemplate\Registry\AgentBlueprintRegistry;
use Throwable;

final class AgentDefinitionFactory
{
    public function __construct(
        private readonly AgentBlueprintRegistry $blueprints,
        private readonly ?string $defaultBlueprint = null,
    ) {}

    public function create(AgentDefinition $definition): AgentInterface {
        $class = $this->resolveBlueprintClass($definition);

        try {
            return $class::fromDefinition($definition);
        } catch (AgentTemplateException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw AgentTemplateException::blueprintCreationFailed(
                $class,
                $definition->name,
                $e,
            );
        }
    }

    /**
     * @return class-string<AgentBlueprint>
     */
    private function resolveBlueprintClass(AgentDefinition $definition): string {
        if ($definition->blueprintClass !== null) {
            if (!class_exists($definition->blueprintClass)) {
                throw AgentTemplateException::blueprintNotFound($definition->blueprintClass);
            }
            if (!is_subclass_of($definition->blueprintClass, AgentBlueprint::class)) {
                throw AgentTemplateException::invalidBlueprint(
                    $definition->blueprintClass,
                    'must implement AgentBlueprint',
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

        throw AgentTemplateException::blueprintMissing($definition->name);
    }
}
