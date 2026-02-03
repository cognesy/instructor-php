<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Registry;

use Cognesy\Agents\AgentTemplate\Contracts\AgentBlueprint;
use Cognesy\Agents\AgentTemplate\Exceptions\AgentTemplateException;

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
     * @param class-string<AgentBlueprint> $class
     */
    public function register(string $alias, string $class): self {
        if (!is_subclass_of($class, AgentBlueprint::class)) {
            throw AgentTemplateException::invalidBlueprint(
                $alias,
                'must implement AgentBlueprint',
            );
        }

        $this->blueprints[$alias] = $class;
        return $this;
    }

    /**
     * @return class-string<AgentBlueprint>
     */
    public function get(string $alias): string {
        if (!$this->has($alias)) {
            throw AgentTemplateException::blueprintNotFound(
                $alias,
                array_keys($this->blueprints),
            );
        }

        return $this->blueprints[$alias];
    }

    public function has(string $alias): bool {
        return isset($this->blueprints[$alias]);
    }

    /** @return array<int, string> */
    public function names(): array {
        return array_keys($this->blueprints);
    }
}
