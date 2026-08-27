<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\TellCapabilityCardinality;
use Cognesy\Tell\Contracts\TellCapabilityContracts;
use Throwable;

/** @internal */
final readonly class TellHostBootstrap
{
    /**
     * @param  list<TellModuleDefinition>  $modules
     * @param  list<class-string>  $requiredCapabilities
     */
    public static function boot(string $profile, array $modules, array $requiredCapabilities): TellHost
    {
        $providerModules = self::admit($modules, $requiredCapabilities);
        $order = self::constructionOrder($modules, $providerModules);
        $providers = [];
        $constructed = [];

        foreach ($order as $index) {
            $module = $modules[$index];
            try {
                $dependencies = [
                    ...self::dependencies($module->requires, $providers, false),
                    ...self::dependencies($module->optional, $providers, true),
                ];
                $instance = $module->create($dependencies);
                $constructed[] = ['id' => $module->id, 'instance' => $instance];
                foreach ($module->provides as $capability) {
                    if (! $instance instanceof $capability) {
                        throw new \UnexpectedValueException("Module {$module->id} does not implement {$capability}.");
                    }
                    $providers[$capability][] = $instance;
                }
            } catch (Throwable $error) {
                throw new TellHostBootException(
                    module: $module->id,
                    cleanupErrors: TellModuleCleanup::dispose($constructed),
                    previous: $error,
                );
            }
        }

        $commandErrors = self::commandContributionErrors($providers);
        if ($commandErrors !== []) {
            $cleanupErrors = TellModuleCleanup::dispose($constructed);
            $cleanupMessages = array_map(static fn (string $error): string => "cleanup {$error}", $cleanupErrors);
            throw new TellHostGraphException([...$commandErrors, ...$cleanupMessages]);
        }

        $description = new TellHostDescription(
            profile: $profile,
            modules: array_map(
                static fn (TellModuleDefinition $module): array => $module->describe(),
                $modules,
            ),
            requiredCapabilities: $requiredCapabilities,
        );

        return new TellHost($providers, $constructed, $description);
    }

    /**
     * Contribution contents become available only after factories run, so they
     * are admitted before a booted host is returned and cleaned up on failure.
     *
     * @param  array<class-string, list<object>>  $providers
     * @return list<string>
     */
    private static function commandContributionErrors(array $providers): array
    {
        $owners = [];
        $errors = [];
        foreach ($providers[CanContributeTellCommands::class] ?? [] as $index => $provider) {
            if (! $provider instanceof CanContributeTellCommands) {
                continue;
            }
            foreach ($provider->commands() as $descriptor) {
                foreach ([$descriptor->name, ...$descriptor->aliases] as $name) {
                    if (isset($owners[$name])) {
                        $errors[] = "duplicate command name {$name} in contributions {$owners[$name]} and {$index}";

                        continue;
                    }
                    $owners[$name] = $index;
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<TellModuleDefinition>  $modules
     * @param  list<class-string>  $requiredCapabilities
     * @return array<class-string, list<int>>
     */
    private static function admit(array $modules, array $requiredCapabilities): array
    {
        $errors = [];
        $ids = [];
        $providerModules = [];

        foreach ($modules as $index => $module) {
            if (isset($ids[$module->id])) {
                $errors[] = "duplicate module id {$module->id}";
            }
            $ids[$module->id] = true;
            foreach ($module->provides as $capability) {
                if (! interface_exists($capability)) {
                    $errors[] = "module {$module->id} advertises non-interface {$capability}";
                }
                $providerModules[$capability][] = $index;
            }
        }

        foreach ($providerModules as $capability => $indices) {
            $cardinality = TellCapabilityContracts::cardinality($capability)
                ?? TellCapabilityCardinality::Singleton;
            if ($cardinality !== TellCapabilityCardinality::OrderedContribution && count($indices) > 1) {
                $ids = array_map(static fn (int $index): string => $modules[$index]->id, $indices);
                $errors[] = "duplicate {$cardinality->value} provider {$capability}: ".implode(', ', $ids);
            }
        }

        foreach ($modules as $module) {
            foreach ($module->requires as $capability) {
                if (! isset($providerModules[$capability])) {
                    $errors[] = "missing {$capability} required by module {$module->id}";
                }
            }
        }
        foreach ($requiredCapabilities as $capability) {
            if (! interface_exists($capability)) {
                $errors[] = "profile requires non-interface {$capability}";

                continue;
            }
            if (! isset($providerModules[$capability])) {
                $errors[] = "missing {$capability} required by profile";
            }
        }

        if ($errors !== []) {
            throw new TellHostGraphException(array_values(array_unique($errors)));
        }

        return $providerModules;
    }

    /**
     * @param  list<TellModuleDefinition>  $modules
     * @param  array<class-string, list<int>>  $providerModules
     * @return list<int>
     */
    private static function constructionOrder(array $modules, array $providerModules): array
    {
        $pending = array_keys($modules);
        $constructed = [];
        $order = [];

        while ($pending !== []) {
            foreach ($pending as $offset => $index) {
                $dependencies = [...$modules[$index]->requires, ...$modules[$index]->optional];
                $ready = true;
                foreach ($dependencies as $capability) {
                    foreach ($providerModules[$capability] ?? [] as $providerIndex) {
                        if (! isset($constructed[$providerIndex])) {
                            $ready = false;
                            break 2;
                        }
                    }
                }
                if (! $ready) {
                    continue;
                }
                $constructed[$index] = true;
                $order[] = $index;
                unset($pending[$offset]);

                continue 2;
            }

            $blocked = array_map(static fn (int $index): string => $modules[$index]->id, $pending);
            throw new TellHostGraphException([
                'construction cycle among modules: '.implode(', ', $blocked),
            ]);
        }

        return $order;
    }

    /**
     * @param  list<class-string>  $capabilities
     * @param  array<class-string, list<object>>  $providers
     * @return list<object|null>
     */
    private static function dependencies(array $capabilities, array $providers, bool $optional): array
    {
        $dependencies = [];
        foreach ($capabilities as $capability) {
            $available = $providers[$capability] ?? [];
            $cardinality = TellCapabilityContracts::cardinality($capability)
                ?? TellCapabilityCardinality::Singleton;
            $dependencies[] = match ($cardinality) {
                TellCapabilityCardinality::OrderedContribution => new TellCapabilityProviders($available),
                default => $available[0] ?? ($optional ? null : throw new \LogicException("Missing admitted dependency {$capability}.")),
            };
        }

        return $dependencies;
    }
}
