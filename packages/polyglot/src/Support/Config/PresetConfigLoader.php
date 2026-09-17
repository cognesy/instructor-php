<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Config\EnvTemplate;
use InvalidArgumentException;

/** @internal */
final class PresetConfigLoader
{
    /**
     * @param list<string> $paths
     * @return list<string>
     */
    public static function names(array $paths): array
    {
        $names = [];
        foreach (BasePath::resolveExisting(...$paths) as $path) {
            foreach (glob(rtrim($path, '/\\').'/*.yaml') ?: [] as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param list<string> $paths
     * @return array<array-key, mixed>
     */
    public static function load(string $preset, array $paths, ?EnvTemplate $template = null): array
    {
        $config = self::config(
            paths: $paths,
            missingPathsMessage: "No preset directory found for '{$preset}'. Searched: ".implode(', ', $paths),
            template: $template,
        );

        return $config->load("{$preset}.yaml")->toArray();
    }

    /** @param list<string> $presetPaths */
    public static function defaultName(string $label, array $presetPaths, ?EnvTemplate $template = null): string
    {
        $paths = array_values(array_unique(array_map(dirname(...), $presetPaths)));
        $config = self::config(
            paths: $paths,
            missingPathsMessage: "No {$label} configuration directory found. Searched: ".implode(', ', $paths),
            template: $template,
        );
        $preset = $config->load('default.yaml')->toArray()['defaultPreset'] ?? null;
        if (! is_string($preset) || trim($preset) === '') {
            throw new InvalidArgumentException("Invalid {$label} default preset selector: expected non-empty string at 'defaultPreset'.");
        }

        return trim($preset);
    }

    /** @param list<string> $paths */
    private static function config(array $paths, string $missingPathsMessage, ?EnvTemplate $template): Config
    {
        $resolvedPaths = BasePath::resolveExisting(...$paths);
        if ($resolvedPaths === []) {
            throw new InvalidArgumentException($missingPathsMessage);
        }
        $config = Config::fromPaths(...$resolvedPaths);

        return $template === null ? $config : $config->withTemplate($template);
    }
}
