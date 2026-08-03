<?php declare(strict_types=1);

namespace Cognesy\Agents\Discovery;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Discovery\Exceptions\CapabilityResolutionException;
use Cognesy\Agents\Discovery\Exceptions\ToolResolutionException;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use ReflectionClass;
use Throwable;

final class CapabilityDiscovery
{
    public static function discover(
        CanManageAgentCapabilities $capabilities,
        CanManageTools $tools,
        ?string $vendorDir = null,
        ?string $rootComposerPath = null,
    ): DiscoveryResult {
        $result = ComposerManifestReader::installed($vendorDir, $rootComposerPath)->read();
        [$capabilityEntries, $result] = self::entries($result, 'capabilities');
        [$toolEntries, $result] = self::entries($result, 'tools');

        foreach ($capabilityEntries as $name => $entry) {
            if ($capabilities->has($name)) {
                $result = $result->withError("Capability '{$name}' is already registered; manifest entry was skipped.");
                continue;
            }
            $capabilities->registerFactory(
                $name,
                static fn (): CanProvideAgentCapability => self::resolveCapability($name, $entry),
            );
            $result = $result->withCapability($name);
        }

        foreach ($toolEntries as $name => $entry) {
            if ($tools->has($name)) {
                $result = $result->withError("Tool '{$name}' is already registered; manifest entry was skipped.");
                continue;
            }
            $tools->registerFactory(
                $name,
                static fn (): ToolInterface => self::resolveTool($name, $entry),
            );
            $result = $result->withTool($name);
        }
        return $result;
    }

    /**
     * @return array{array<string, array{package: string, class: class-string}>, DiscoveryResult}
     */
    private static function entries(DiscoveryResult $result, string $type): array {
        $entries = [];
        foreach ($result->manifests()->all() as $manifest) {
            $manifestEntries = match ($type) {
                'capabilities' => $manifest->capabilities,
                'tools' => $manifest->tools,
            };
            foreach ($manifestEntries as $name => $class) {
                if (isset($entries[$name]) && !$manifest->root) {
                    $owner = $entries[$name]['package'];
                    $result = $result->withError(
                        "Duplicate {$type} name '{$name}' from '{$manifest->packageName}'; '{$owner}' wins.",
                    );
                    continue;
                }
                $entries[$name] = ['package' => $manifest->packageName, 'class' => $class];
            }
        }
        return [$entries, $result];
    }

    /** @param array{package: string, class: class-string} $entry */
    private static function resolveCapability(
        string $name,
        array $entry,
    ): CanProvideAgentCapability {
        $instance = self::instantiate($entry['package'], 'capability', $name, $entry['class']);
        if (!$instance instanceof CanProvideAgentCapability) {
            throw new CapabilityResolutionException(self::wrongInterfaceMessage(
                $entry['package'],
                'capability',
                $name,
                $entry['class'],
                CanProvideAgentCapability::class,
            ));
        }
        return $instance;
    }

    /** @param array{package: string, class: class-string} $entry */
    private static function resolveTool(string $name, array $entry): ToolInterface {
        $instance = self::instantiate($entry['package'], 'tool', $name, $entry['class']);
        if (!$instance instanceof ToolInterface) {
            throw new ToolResolutionException(self::wrongInterfaceMessage(
                $entry['package'],
                'tool',
                $name,
                $entry['class'],
                ToolInterface::class,
            ));
        }
        return $instance;
    }

    /** @param class-string $class */
    private static function instantiate(
        string $package,
        string $type,
        string $name,
        string $class,
    ): object {
        $exceptionClass = match ($type) {
            'capability' => CapabilityResolutionException::class,
            'tool' => ToolResolutionException::class,
        };
        if (!class_exists($class)) {
            throw new $exceptionClass(
                "Package '{$package}' {$type} '{$name}' references missing class '{$class}'.",
            );
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$reflection->isInstantiable() || $constructor?->getNumberOfRequiredParameters() > 0) {
            throw new $exceptionClass(
                "Package '{$package}' {$type} '{$name}' class '{$class}' must be constructible without arguments; register a factory instead.",
            );
        }

        try {
            return $reflection->newInstance();
        } catch (Throwable $throwable) {
            throw new $exceptionClass(
                "Package '{$package}' {$type} '{$name}' class '{$class}' failed to instantiate: {$throwable->getMessage()}",
                previous: $throwable,
            );
        }
    }

    /** @param class-string $class */
    private static function wrongInterfaceMessage(
        string $package,
        string $type,
        string $name,
        string $class,
        string $interface,
    ): string {
        return "Package '{$package}' {$type} '{$name}' class '{$class}' must implement {$interface}.";
    }
}
