<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Registry;

use Cognesy\Addons\Agent\Contracts\AgentCapability;
use InvalidArgumentException;

final class AgentCapabilityRegistry
{
    /** @var array<string, callable(): AgentCapability> */
    private array $factories = [];

    /** @var array<string, AgentCapability> */
    private array $instances = [];

    public function register(string $code, AgentCapability $capability): self
    {
        $this->instances[$code] = $capability;
        return $this;
    }

    /**
     * @param callable(): AgentCapability $factory
     */
    public function registerFactory(string $code, callable $factory): self
    {
        $this->factories[$code] = $factory;
        return $this;
    }

    public function has(string $code): bool
    {
        return isset($this->instances[$code]) || isset($this->factories[$code]);
    }

    public function resolve(string $code): AgentCapability
    {
        if (isset($this->instances[$code])) {
            return $this->instances[$code];
        }

        if (isset($this->factories[$code])) {
            $capability = ($this->factories[$code])();
            if (!$capability instanceof AgentCapability) {
                throw new InvalidArgumentException(
                    "Capability factory for '{$code}' must return AgentCapability."
                );
            }
            $this->instances[$code] = $capability;
            return $capability;
        }

        throw new InvalidArgumentException("Capability '{$code}' not found.");
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        $names = array_merge(
            array_keys($this->factories),
            array_keys($this->instances),
        );

        $unique = [];
        foreach ($names as $name) {
            if (!isset($unique[$name])) {
                $unique[$name] = true;
            }
        }

        return array_keys($unique);
    }
}
