<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use InvalidArgumentException;

/** Immutable pre-boot graph editor. */
final readonly class TellHostBuilder
{
    /**
     * @param  list<TellModuleDefinition>  $modules
     * @param  list<class-string>  $requiredCapabilities
     */
    private function __construct(
        private string $profile,
        private array $modules,
        private array $requiredCapabilities,
    ) {}

    public static function fromProfile(TellHostProfile $profile): self {
        return new self($profile->name, $profile->modules, $profile->requiredCapabilities);
    }

    public static function empty(string $profile = 'custom'): self {
        return self::fromProfile(TellHostProfile::empty($profile));
    }

    public function with(TellModuleDefinition $module): self {
        return new self($this->profile, [...$this->modules, $module], $this->requiredCapabilities);
    }

    public function replace(string $moduleId, TellModuleDefinition $replacement): self {
        $modules = $this->modules;
        foreach ($modules as $index => $module) {
            if ($module->id !== $moduleId) {
                continue;
            }
            $modules[$index] = $replacement;

            return new self($this->profile, array_values($modules), $this->requiredCapabilities);
        }

        throw new InvalidArgumentException("Tell module {$moduleId} cannot be replaced because it is not in the graph.");
    }

    public function without(string $moduleId): self {
        $modules = array_values(array_filter(
            $this->modules,
            static fn (TellModuleDefinition $module): bool => $module->id !== $moduleId,
        ));
        if (count($modules) === count($this->modules)) {
            throw new InvalidArgumentException("Tell module {$moduleId} cannot be removed because it is not in the graph.");
        }

        return new self($this->profile, $modules, $this->requiredCapabilities);
    }

    /** @param class-string ...$capabilities */
    public function require(string ...$capabilities): self {
        return new self(
            $this->profile,
            $this->modules,
            array_values(array_unique([...$this->requiredCapabilities, ...$capabilities])),
        );
    }

    public function boot(): TellHost {
        return TellHostBootstrap::boot($this->profile, $this->modules, $this->requiredCapabilities);
    }
}
