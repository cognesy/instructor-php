<?php declare(strict_types=1);

namespace Cognesy\Setup\Config;

use InvalidArgumentException;

final class PublishedConfigRules
{
    /** @param array<string, mixed> $config */
    public function __invoke(array $config): array
    {
        foreach ($config as $package => $packageConfig) {
            $packageRules = $this->rules()[$package] ?? null;
            if ($packageRules === null) {
                throw new InvalidArgumentException("Unknown config namespace: {$package}");
            }

            if (!is_array($packageConfig)) {
                throw new InvalidArgumentException("Config namespace must map to an array: {$package}");
            }

            $this->validatePackage((string) $package, $packageConfig, $packageRules);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $packageConfig
     * @param array<string, true|list<string>> $packageRules
     */
    private function validatePackage(string $package, array $packageConfig, array $packageRules): void
    {
        foreach ($packageConfig as $section => $value) {
            $rule = $packageRules[$section] ?? null;
            if ($rule === null) {
                throw new InvalidArgumentException("Unknown config path: {$package}.{$section}");
            }

            if ($rule === true) {
                $this->assertArrayLeaf("{$package}.{$section}", $value);
                continue;
            }

            $this->validateGroupedSection("{$package}.{$section}", $value, $rule);
        }
    }

    /**
     * @param mixed $sectionValue
     * @param list<string> $allowedChildren
     */
    private function validateGroupedSection(string $path, mixed $sectionValue, array $allowedChildren): void
    {
        if (!is_array($sectionValue)) {
            throw new InvalidArgumentException("Grouped config section must be an array: {$path}");
        }

        foreach ($sectionValue as $child => $childValue) {
            if (!in_array($child, $allowedChildren, true)) {
                throw new InvalidArgumentException("Unknown config path: {$path}.{$child}");
            }

            if ($child === 'default') {
                $this->assertArrayLeaf("{$path}.default", $childValue);
                continue;
            }

            if (!is_array($childValue)) {
                throw new InvalidArgumentException("Config group must be an array map: {$path}.{$child}");
            }

            foreach ($childValue as $name => $entry) {
                $this->assertArrayLeaf("{$path}.{$child}.{$name}", $entry);
            }
        }

        if (in_array('default', $allowedChildren, true) && !array_key_exists('default', $sectionValue)) {
            throw new InvalidArgumentException("Missing required default config: {$path}.default");
        }
    }

    private function assertArrayLeaf(string $path, mixed $value): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Config entry must be an array: {$path}");
        }
    }

    /**
     * @return array<string, array<string, true|list<string>>>
     */
    private function rules(): array
    {
        return [
            'auxiliary' => [
                'web' => ['default', 'scrapers'],
            ],
            'doctools' => [
                'docs' => true,
                'quality' => ['profiles'],
            ],
            'http-client' => [
                'debug' => ['default', 'presets'],
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
                'http' => ['default', 'presets'],
            ],
            'http-pool' => [
                'pool' => ['default', 'presets'],
            ],
            'hub' => [
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
            ],
            'instructor' => [
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
                'structured' => ['default', 'presets'],
            ],
            'laravel' => [
                'instructor' => true,
                'instructor-logging' => true,
            ],
            'polyglot' => [
                'docs' => true,
                'embed' => ['default', 'presets'],
                'examples' => true,
                'examples-groups' => true,
                'llm' => ['default', 'presets'],
            ],
            'setup' => [
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
            ],
            'tell' => [
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
            ],
            'templates' => [
                'docs' => true,
                'examples' => true,
                'examples-groups' => true,
                'prompt' => ['default', 'presets'],
            ],
        ];
    }
}
