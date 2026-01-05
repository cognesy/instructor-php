<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Agents;

use InvalidArgumentException;

final class AgentPresets
{
    private static ?string $presetsDir = null;
    private static ?AgentSpecParser $parser = null;

    /**
     * Get an agent spec by preset name or file path.
     *
     * @param string $nameOrPath Preset name (e.g., 'coordinator') or absolute path to .md file
     * @return AgentSpec
     * @throws InvalidArgumentException if preset not found
     */
    public static function get(string $nameOrPath): AgentSpec {
        // If it's an absolute path, load directly
        if (str_starts_with($nameOrPath, '/') && file_exists($nameOrPath)) {
            return self::parser()->parseMarkdownFile($nameOrPath);
        }

        // Otherwise, treat as preset name
        $presetPath = self::presetsDir() . '/' . $nameOrPath . '.md';

        if (!file_exists($presetPath)) {
            throw new InvalidArgumentException(
                "Agent preset '{$nameOrPath}' not found at: {$presetPath}"
            );
        }

        return self::parser()->parseMarkdownFile($presetPath);
    }

    /**
     * Check if a preset exists.
     */
    public static function has(string $name): bool {
        $presetPath = self::presetsDir() . '/' . $name . '.md';
        return file_exists($presetPath);
    }

    /**
     * Get all available preset names.
     *
     * @return array<string>
     */
    public static function names(): array {
        $dir = self::presetsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.md');
        if ($files === false) {
            return [];
        }

        return array_map(
            fn($file) => basename($file, '.md'),
            $files
        );
    }

    /**
     * Load all presets into a registry.
     */
    public static function loadInto(AgentRegistry $registry): void {
        foreach (self::names() as $name) {
            $spec = self::get($name);
            $registry->register($spec);
        }
    }

    /**
     * Set custom presets directory (for testing or custom presets).
     */
    public static function setPresetsDir(string $dir): void {
        self::$presetsDir = $dir;
    }

    /**
     * Get the presets directory path.
     */
    private static function presetsDir(): string {
        if (self::$presetsDir !== null) {
            return self::$presetsDir;
        }

        // Default to package agents directory
        return __DIR__ . '/../../../agents';
    }

    /**
     * Get the parser instance.
     */
    private static function parser(): AgentSpecParser {
        if (self::$parser === null) {
            self::$parser = new AgentSpecParser();
        }
        return self::$parser;
    }
}
