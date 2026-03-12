<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Discovery;

use Cognesy\Xprompt\Attributes\AsPrompt;
use Cognesy\Xprompt\Prompt;
use Cognesy\Xprompt\PromptRegistry;
use ReflectionClass;

class PromptDiscovery
{
    /**
     * Discover Prompt subclasses from Composer's classmap and register them.
     *
     * @param list<string> $namespaces Namespace prefixes to scan (empty = all)
     */
    public static function register(PromptRegistry $registry, array $namespaces = []): void
    {
        $classes = self::discoverClasses($namespaces);

        foreach ($classes as $class) {
            $name = self::resolveName($class, $namespaces);
            if ($name !== null) {
                $registry->register($name, $class);
            }
        }
    }

    /**
     * Resolve the prompt name for a class.
     *
     * Priority: #[AsPrompt] > $promptName property > FQCN convention.
     *
     * @param list<string> $namespaces
     */
    public static function resolveName(string $class, array $namespaces = []): ?string
    {
        $ref = new ReflectionClass($class);

        // 1. #[AsPrompt("name")] attribute
        $attrs = $ref->getAttributes(AsPrompt::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance()->name;
        }

        // 2. $promptName property
        if ($ref->hasProperty('promptName')) {
            $prop = $ref->getProperty('promptName');
            if ($prop->isPublic() && $prop->hasDefaultValue()) {
                $val = $prop->getDefaultValue();
                if (is_string($val) && $val !== '') {
                    return $val;
                }
            }
        }

        // 3. Convention: derive from FQCN
        return self::deriveNameFromClass($class, $namespaces);
    }

    /**
     * Derive a dotted name from a fully qualified class name.
     *
     * Strips the matching namespace prefix, converts remaining segments
     * to snake_case, and joins with dots.
     *
     * Example: App\Prompts\Reviewer\AnalyzeDocument -> reviewer.analyze_document
     */
    public static function deriveNameFromClass(string $class, array $namespaces = []): string
    {
        // Strip matching namespace prefix
        $relative = $class;
        foreach ($namespaces as $ns) {
            $prefix = rtrim($ns, '\\') . '\\';
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                break;
            }
        }

        // Split into segments
        $segments = explode('\\', $relative);

        // Convert each segment to snake_case
        $segments = array_map(
            fn(string $s): string => self::toSnakeCase($s),
            $segments,
        );

        return implode('.', $segments);
    }

    // -- Private --------------------------------------------------------

    /**
     * @param list<string> $namespaces
     * @return list<class-string<Prompt>>
     */
    private static function discoverClasses(array $namespaces): array
    {
        $classMap = self::getComposerClassMap();
        $classes = [];

        foreach ($classMap as $class => $file) {
            // Namespace filter
            if ($namespaces !== [] && !self::matchesNamespace($class, $namespaces)) {
                continue;
            }

            // Must be a concrete Prompt subclass
            if (!class_exists($class, true)) {
                continue;
            }

            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || !$ref->isSubclassOf(Prompt::class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @return array<string, string>
     */
    private static function getComposerClassMap(): array
    {
        $autoloaders = spl_autoload_functions();
        foreach ($autoloaders as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                return $autoloader[0]->getClassMap();
            }
        }
        return [];
    }

    /**
     * @param list<string> $namespaces
     */
    private static function matchesNamespace(string $class, array $namespaces): bool
    {
        foreach ($namespaces as $ns) {
            $prefix = rtrim($ns, '\\') . '\\';
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private static function toSnakeCase(string $input): string
    {
        // Insert underscore before uppercase letters, lowercase everything
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $input);
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
