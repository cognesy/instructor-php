<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Registry;

use Cognesy\Addons\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Addons\AgentTemplate\Exceptions\AgentBlueprintNotFoundException;
use Cognesy\Addons\AgentTemplate\Exceptions\InvalidAgentBlueprintException;

final class AgentBlueprintRegistry
{
    /** @var array<string, class-string<AgentBlueprint>> */
    private array $blueprints = [];

    /**
     * @param array<string, class-string<AgentBlueprint>> $blueprints
     */
    public function __construct(array $blueprints = []) {
        foreach ($blueprints as $alias => $class) {
            $this->register($alias, $class);
        }
    }

    /**
     * Register a blueprint alias.
     *
     * @param string $alias
     * @param class-string<AgentBlueprint> $class
     */
    public function register(string $alias, string $class): self {
        if (!is_subclass_of($class, AgentBlueprint::class)) {
            throw new InvalidAgentBlueprintException(
                "Blueprint '{$alias}' must implement AgentBlueprint."
            );
        }

        $this->blueprints[$alias] = $class;
        return $this;
    }

    /**
     * Resolve a blueprint class by alias.
     *
     * @return class-string<AgentBlueprint>
     */
    public function get(string $alias): string {
        if (!$this->has($alias)) {
            $available = implode(', ', array_keys($this->blueprints));
            throw new AgentBlueprintNotFoundException(
                "Blueprint '{$alias}' not found. Available: {$available}"
            );
        }

        return $this->blueprints[$alias];
    }

    public function has(string $alias): bool {
        return isset($this->blueprints[$alias]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array {
        return array_keys($this->blueprints);
    }
}
